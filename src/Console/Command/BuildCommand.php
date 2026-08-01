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
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\ClassFinder\ClassListenerProviderInterface;
use Componenta\ClassFinder\Compile\ConfigKey as ClassFinderCompileConfigKey;
use Componenta\Config\Config;
use Componenta\Config\ConfigLoader;
use Componenta\DI\Compile\PlanCompiler;
use Componenta\DI\Compile\PlanDispatcher;
use Componenta\DI\ConfigKey as DiConfigKey;
use Componenta\DI\ContainerBuilder;
use Componenta\Stdlib\PathResolverInterface;
use Componenta\VarExport\Export;
use Psr\Container\ContainerInterface;
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
    public function __construct(
        private readonly Config $config,
        private readonly PathResolverInterface $paths,
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->config->environment?->match('APP_ENV', 'development', false) !== true) {
            throw new RuntimeException('app:build must run with APP_ENV=development so it can build from source configuration and discovery metadata.');
        }

        $cache = CacheLayout::fromConfig($this->config, $this->paths);
        $config = config_merge($this->config->toArray(), $this->compileDiscoveryDelta($cache));
        $dependencies = $config[DiConfigKey::DEPENDENCIES] ?? [];

        if (!is_array($dependencies)) {
            throw new RuntimeException('DI dependencies config must be an array.');
        }

        unset($config[DiConfigKey::DEPENDENCIES]);

        ConfigLoader::export(new Config($config, $this->config->environment), $cache->config);
        AtomicFile::replace($cache->container, $this->phpReturn([
            'version' => ContainerBuilder::CACHE_VERSION,
            DiConfigKey::DEPENDENCIES => ContainerBuilder::normalizeDependencies($dependencies),
        ]), 'container cache');

        $io->success([
            sprintf('Config cache: %s', $cache->config),
            sprintf('Container cache: %s', $cache->container),
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

        $delta = [
            ListenerRestorer::CACHE_KEY => $discoveryCache,
            DiConfigKey::DEPENDENCIES => [
                PlanCompiler::CONFIG_KEY => $diPlans,
                PlanDispatcher::CONFIG_KEY => $dispatcherMap,
            ],
        ];

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

            $contributions[] = $contributor->compile($classes);
        }

        return $contributions;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function phpReturn(array $data): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . Export::pretty($data) . ";\n";
    }
}
