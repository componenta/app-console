<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Componenta\App\Cache\AtomicFile;
use Componenta\App\Cache\CacheLayout;
use Componenta\App\ConfigKey;
use Componenta\App\Discovery\Compile\CompileCacheContributorInterface;

use Componenta\App\Discovery\Compile\DiscoveryCompiler;
use Componenta\App\Discovery\ListenerCompiler;
use Componenta\App\Discovery\ListenerRestorer;
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\ClassFinder\ClassListenerProviderInterface;
use Componenta\ClassFinder\Compile\ConfigKey as ClassFinderCompileConfigKey;
use Componenta\Config\Config;
use Componenta\Config\ConfigLoader;
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
        $compiled = $this->compileDiscovery($cache);
        $config = config_merge($this->config->toArray(), $compiled['delta']);
        $dependencies = $config[DiConfigKey::DEPENDENCIES] ?? [];

        if (!is_array($dependencies)) {
            throw new RuntimeException('DI dependencies config must be an array.');
        }

        $entryClasses = $this->entryClasses($compiled['iterator']);
        $generatedResolver = false;

        if ($entryClasses !== []) {
            $releaseFingerprint = bin2hex(random_bytes(32));
            $builder = ContainerBuilder::configureWithDependencies(
                new Config($config, $this->config->environment),
                $dependencies,
            );
            $builder->addService(PathResolverInterface::class, $this->paths);

            if ($compiled['iterator'] !== null) {
                $builder->addService(ClassIteratorInterface::class, $compiled['iterator']);
            }

            $builder->compileGeneratedEntryResolver(
                $entryClasses,
                $cache->containerResolver,
                releaseFingerprint: $releaseFingerprint,
            );
            $dependencies[DiConfigKey::GENERATED_ENTRY_RESOLVER_FILE]
                = CacheLayout::CONTAINER_RESOLVER;
            $dependencies[DiConfigKey::GENERATED_ENTRY_RESOLVER_RELEASE_FINGERPRINT]
                = $releaseFingerprint;
            $generatedResolver = true;
        } else {
            unset(
                $dependencies[DiConfigKey::GENERATED_ENTRY_RESOLVER_FILE],
                $dependencies[DiConfigKey::GENERATED_ENTRY_RESOLVER_RELEASE_FINGERPRINT],
            );

            if (is_file($cache->containerResolver) && !unlink($cache->containerResolver)) {
                throw new RuntimeException(sprintf(
                    'Cannot remove stale generated container resolver: %s',
                    $cache->containerResolver,
                ));
            }
        }

        unset($config[DiConfigKey::DEPENDENCIES]);

        ConfigLoader::export(new Config($config, $this->config->environment), $cache->config);
        AtomicFile::replace($cache->container, $this->phpReturn([
            'version' => ContainerBuilder::CACHE_VERSION,
            DiConfigKey::DEPENDENCIES => ContainerBuilder::normalizeDependencies($dependencies),
        ]), 'container cache');

        $artifacts = [
            sprintf('Config cache: %s', $cache->config),
            sprintf('Container cache: %s', $cache->container),
        ];

        if ($generatedResolver) {
            $artifacts[] = sprintf('Generated container resolver: %s', $cache->containerResolver);
        }

        $io->success($artifacts);

        return Command::SUCCESS;
    }

    /**
     * @return array{
     *     delta: array<string, mixed>,
     *     classes: list<class-string>,
     *     iterator: ?ClassIteratorInterface
     * }
     */
    private function compileDiscovery(CacheLayout $cache): array
    {
        if (!$this->container->has(ClassIteratorInterface::class)) {
            if ($this->hasDiscoveryWork()) {
                throw new RuntimeException(sprintf(
                    'Cannot build discovery cache: %s is not available while discovery listeners, listener compilers, or compile contributors are configured.',
                    ClassIteratorInterface::class,
                ));
            }

            return [
                'delta' => [],
                'classes' => [],
                'iterator' => null,
            ];
        }

        $iterator = $this->container->get(ClassIteratorInterface::class);

        if (!$iterator instanceof ClassIteratorInterface) {
            throw new RuntimeException(sprintf(
                '%s container entry must implement %s.',
                ClassIteratorInterface::class,
                ClassIteratorInterface::class,
            ));
        }

        $discoveryCache = $this->container->get(ListenerCompiler::class)->compile($iterator);
        $delta = $discoveryCache['classes'] === [] && $discoveryCache['targets'] === []
            ? []
            : [ListenerRestorer::CACHE_KEY => $discoveryCache];

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

        return [
            'delta' => $delta,
            'classes' => $discoveryCache['classes'],
            'iterator' => $iterator,
        ];
    }

    /** @return list<class-string> */
    private function entryClasses(?ClassIteratorInterface $classes): array
    {
        if ($classes === null) {
            return [];
        }

        $entries = [];

        foreach ($classes as $class) {
            if ($class->isConcrete) {
                $entries[$class->fullyQualifiedName] = true;
            }
        }

        ksort($entries);

        return array_keys($entries);
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
