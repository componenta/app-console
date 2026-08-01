<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Componenta\App\Cache\AtomicFile;
use Componenta\App\Cache\CacheLayout;
use Componenta\App\ConfigKey;
use Componenta\App\Discovery\Compile\CompileCacheContributorInterface;
use Componenta\App\Discovery\Compile\DiPlanBuilder;
use Componenta\App\Discovery\Compile\DiscoveryCompiler;
use Componenta\App\Discovery\ListenerCompiler;
use Componenta\App\Discovery\ListenerRestorer;
use Componenta\ClassFinder\Attribute\DevOnly;
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\ClassFinder\ClassListenerProviderInterface;
use Componenta\ClassFinder\Compile\ConfigKey as ClassFinderCompileConfigKey;
use Componenta\Config\Config;
use Componenta\Config\FileValue;
use Componenta\Config\ConfigLoader;
use Componenta\DI\Compile\IndexedPlanCacheGenerator;
use Componenta\DI\Compile\PlanCompiler;
use Componenta\DI\Compile\PlanDispatcher;
use Componenta\DI\ConfigKey as DiConfigKey;
use Componenta\DI\ContainerBuilder;
use Componenta\Stdlib\PathResolverInterface;
use Componenta\VarExport\Export;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Componenta\Config\config_merge;

#[AsCommand(
    name: 'app:build',
    description: 'Build application config and container cache files',
)]
final class BuildCommand extends Command
{
    private const int CONFIG_SHARD_MIN_BYTES = 16_384;

    private readonly IndexedPlanCacheGenerator $planCacheGenerator;

    public function __construct(
        private readonly Config $config,
        private readonly PathResolverInterface $paths,
        private readonly ContainerInterface $container,
        ?IndexedPlanCacheGenerator $planCacheGenerator = null,
    ) {
        $this->planCacheGenerator = $planCacheGenerator ?? new IndexedPlanCacheGenerator();
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (getenv('COMPONENTA_BUILD') !== '1'
            && $this->config->environment?->match('APP_ENV', 'development', false) !== true) {
            throw new RuntimeException(
                'app:build requires APP_ENV=development or the process-local COMPONENTA_BUILD=1 flag.',
            );
        }

        $cache = CacheLayout::fromConfig($this->config, $this->paths);
        $config = config_merge($this->config->toArray(), $this->compileDiscoveryDelta($cache));
        $dependencies = $config[DiConfigKey::DEPENDENCIES] ?? [];

        if (($config[ConfigKey::RUNTIME_DISCOVERY] ?? true) === false) {
            unset($config[ListenerRestorer::CACHE_KEY]);
        }

        if (!is_array($dependencies)) {
            throw new RuntimeException('DI dependencies config must be an array.');
        }

        if (isset($dependencies[PlanCompiler::FILE_CONFIG_KEY])) {
            unset($dependencies[PlanCompiler::CONFIG_KEY]);
        }

        unset($config[DiConfigKey::DEPENDENCIES]);
        $config = $this->shardConfig($config, $cache);

        ConfigLoader::export(new Config($config, $this->config->environment), $cache->config);
        AtomicFile::replace($cache->container, $this->phpReturn([
            'version' => ContainerBuilder::CACHE_VERSION,
            DiConfigKey::DEPENDENCIES => ContainerBuilder::normalizeDependencies($dependencies),
        ]), 'container cache');

        $io->success([
            sprintf('Config cache: %s', $cache->config),
            sprintf('Container cache: %s', $cache->container),
            sprintf('DI plan cache: %s', $cache->diPlans),
        ]);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function compileDiscoveryDelta(CacheLayout $cache): array
    {
        if (!$this->container->has(ClassIteratorInterface::class)) {
            if ($this->hasDiscoveryWork()) {
                throw new RuntimeException(sprintf(
                    'Cannot build discovery cache: %s is not available while discovery listeners, listener compilers, or compile contributors are configured.',
                    ClassIteratorInterface::class,
                ));
            }

            return [];
        }

        /** @var ClassIteratorInterface $iterator */
        $iterator = $this->container->get(ClassIteratorInterface::class);
        $discoveryCache = $this->container->get(ListenerCompiler::class)->compile($iterator);
        $diPlanBuilder = $this->container->get(DiPlanBuilder::class);
        $diPlans = $diPlanBuilder->compile($discoveryCache['classes']);
        $dispatcherMap = $diPlanBuilder->dispatcherMap();
        $this->planCacheGenerator->generate($diPlans, $cache->diPlans);

        $runtimeDiscovery = $this->requiresRuntimeDiscovery();
        $delta = [
            ConfigKey::RUNTIME_DISCOVERY => $runtimeDiscovery,
            DiConfigKey::DEPENDENCIES => [
                PlanCompiler::FILE_CONFIG_KEY => basename($cache->diPlans),
                PlanDispatcher::CONFIG_KEY => $dispatcherMap,
            ],
        ];

        if ($runtimeDiscovery) {
            $delta[ListenerRestorer::CACHE_KEY] = $discoveryCache;
        }

        if ($this->container->has(ClassListenerProviderInterface::class)
            && $this->container->has(DiscoveryCompiler::class)
        ) {
            /** @var ClassListenerProviderInterface $provider */
            $provider = $this->container->get(ClassListenerProviderInterface::class);
            /** @var DiscoveryCompiler $compiler */
            $compiler = $this->container->get(DiscoveryCompiler::class);

            $delta = config_merge($delta, $compiler->compile(
                $provider->getClassListeners(),
                dirname($cache->config),
            ));
        }

        foreach ($this->compileContributors($discoveryCache['classes']) as $contribution) {
            $delta = config_merge($delta, $contribution);
        }

        return $delta;
    }

    private function hasDiscoveryWork(): bool
    {
        foreach ([
            ClassFinderConfigKey::LISTENERS,
            ClassFinderCompileConfigKey::LISTENER_COMPILERS,
            ConfigKey::COMPILE_CACHE_CONTRIBUTORS,
        ] as $key) {
            $entries = $this->config->get($key, []);

            if (is_array($entries) && $entries !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, class-string> $classes
     * @return list<array<string, mixed>>
     */
    private function compileContributors(array $classes): array
    {
        $entries = $this->config->get(ConfigKey::COMPILE_CACHE_CONTRIBUTORS, []);

        if (!is_array($entries)) {
            throw new RuntimeException(sprintf('%s config value must be an array.', ConfigKey::COMPILE_CACHE_CONTRIBUTORS));
        }

        $contributions = [];

        foreach ($entries as $entry) {
            $contributor = is_string($entry) ? $this->container->get($entry) : $entry;

            if (!$contributor instanceof CompileCacheContributorInterface) {
                throw new RuntimeException(sprintf(
                    'Compile cache contributor must implement %s.',
                    CompileCacheContributorInterface::class,
                ));
            }

            $contributions[] = $contributor->compile(array_values($classes));
        }

        return $contributions;
    }

    private function requiresRuntimeDiscovery(): bool
    {
        if (!$this->container->has(ClassListenerProviderInterface::class)) {
            return true;
        }

        /** @var ClassListenerProviderInterface $provider */
        $provider = $this->container->get(ClassListenerProviderInterface::class);

        foreach ($provider->getClassListeners() as $listener) {
            if ((new ReflectionClass($listener))->getAttributes(DevOnly::class) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function shardConfig(array $config, CacheLayout $cache): array
    {
        foreach ($config as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            try {
                $size = strlen(serialize($value));
            } catch (\Throwable) {
                continue;
            }

            if ($size < self::CONFIG_SHARD_MIN_BYTES) {
                continue;
            }

            $content = $this->phpReturn($value);
            $file = $cache->build(sprintf(
                'config-shard-%s.php',
                substr(hash('sha256', $content), 0, 16),
            ));
            AtomicFile::replace($file, $content, sprintf('config shard "%s"', $key));
            $config[$key] = new FileValue(basename($file));
        }

        return $config;
    }

    /**
     * @param mixed $data
     */
    private function phpReturn(mixed $data): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . Export::pretty($data) . ";\n";
    }
}
