<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Closure;
use Componenta\App\Cache\AtomicFile;
use Componenta\App\Cache\CacheLayout;
use Componenta\App\Config\ConfigFactory;
use Componenta\App\Config\ConfigDefinitionInterface;
use Componenta\App\Config\ConfigFactoryResult;
use Componenta\App\ConfigKey;
use Componenta\App\ContainerCacheMode;
use Componenta\App\ContainerFactory;
use Componenta\App\ContainerFactoryOptions;
use Componenta\App\Discovery\Compile\CompileCacheContributorInterface;
use Componenta\App\Discovery\Compile\DiscoveryCompiler;
use Componenta\App\Discovery\ListenerCompiler;
use Componenta\App\Discovery\ListenerRestorer;
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\ClassFinder\ClassListenerNotifier;
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
    /**
     * @param null|Closure(): mixed $sourceFactory
     */
    public function __construct(
        private readonly Config $config,
        private readonly PathResolverInterface $paths,
        private readonly ?Closure $sourceFactory = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->config->environment?->match('APP_ENV', 'development', false) !== true) {
            throw new RuntimeException('app:build must run with APP_ENV=development so it can build from source configuration and discovery metadata.');
        }

        $source = $this->source();
        $sourceConfig = $source->config;
        $cache = CacheLayout::fromConfig($sourceConfig, $this->paths);
        $buildContainer = ContainerFactory::create(
            paths: $this->paths,
            config: $sourceConfig,
            discovered: $source->discovered,
            options: new ContainerFactoryOptions(ContainerCacheMode::Disabled),
        );

        if ($source->discovered !== null
            && $this->hasDiscoveryWork($sourceConfig)
            && $buildContainer->has(ClassListenerNotifier::class)
        ) {
            $notifier = $buildContainer->get(ClassListenerNotifier::class);

            if ($notifier instanceof ClassListenerNotifier) {
                $notifier->notify($source->discovered);
            }
        }

        $compiled = $this->compileDiscovery($cache, $sourceConfig, $buildContainer, $source->discovered);
        $config = self::mergeConfigDelta($sourceConfig->toArray(), $compiled['delta']);
        $dependencies = self::stringKeyedArray(
            $config[DiConfigKey::DEPENDENCIES] ?? [],
            'DI dependencies config',
        );

        $entryClasses = $this->entryClasses($compiled['iterator']);
        $generatedResolver = false;

        if ($entryClasses !== []) {
            $releaseFingerprint = bin2hex(random_bytes(32));
            $builder = ContainerBuilder::configureWithDependencies(
                new Config($config, $sourceConfig->environment),
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

        ConfigLoader::export(new Config($config, $sourceConfig->environment), $cache->config);
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

    private function source(): ConfigFactoryResult
    {
        if ($this->sourceFactory !== null) {
            $source = ($this->sourceFactory)();

            if (!$source instanceof ConfigFactoryResult) {
                throw new RuntimeException(sprintf(
                    'Build source factory must return %s, got %s.',
                    ConfigFactoryResult::class,
                    get_debug_type($source),
                ));
            }

            return $source;
        }

        $paths = $this->paths;

        return ConfigFactory::create(
            paths: $paths,
            definition: static function () use ($paths): ConfigDefinitionInterface {
                $definition = require $paths->resolve('config/config.php');

                if (!$definition instanceof ConfigDefinitionInterface) {
                    throw new RuntimeException(sprintf(
                        'Source config definition must implement %s; got %s.',
                        ConfigDefinitionInterface::class,
                        get_debug_type($definition),
                    ));
                }

                return $definition;
            },
            environment: $this->config->environment,
            loadCachedCompileDelta: false,
        );
    }

    /**
     * @return array{
     *     delta: array<string, mixed>,
     *     iterator: ?ClassIteratorInterface
     * }
     */
    private function compileDiscovery(
        CacheLayout $cache,
        Config $config,
        ContainerInterface $container,
        ?ClassIteratorInterface $iterator,
    ): array {
        if ($iterator === null) {
            if ($this->hasDiscoveryWork($config)) {
                throw new RuntimeException(sprintf(
                    'Cannot build discovery cache: %s is not available while discovery listeners, listener compilers, or compile contributors are configured.',
                    ClassIteratorInterface::class,
                ));
            }

            return [
                'delta' => [],
                'iterator' => null,
            ];
        }

        $discoveryCache = [];

        $hasDiscoveryWork = $this->hasDiscoveryWork($config);
        if ($hasDiscoveryWork && $container->has(ListenerCompiler::class)) {
            $listenerCompiler = $container->get(ListenerCompiler::class);

            if (!$listenerCompiler instanceof ListenerCompiler) {
                throw new RuntimeException(sprintf(
                    '%s container entry must be %s.',
                    ListenerCompiler::class,
                    ListenerCompiler::class,
                ));
            }

            $discoveryCache = $listenerCompiler->compile($iterator);
        } elseif ($hasDiscoveryWork) {
            throw new RuntimeException(sprintf(
                'Cannot build discovery cache: %s is not available.',
                ListenerCompiler::class,
            ));
        }

        $classes = $discoveryCache['classes'] ?? [];
        $hasFilteredListeners = isset($discoveryCache['targets']) || isset($discoveryCache['empty_targets']);
        $delta = $classes === [] && !$hasFilteredListeners
            ? []
            : [ListenerRestorer::CACHE_KEY => $discoveryCache];

        if ($container->has(ClassListenerProviderInterface::class)
            && $container->has(DiscoveryCompiler::class)
        ) {
            $provider = $container->get(ClassListenerProviderInterface::class);
            $compiler = $container->get(DiscoveryCompiler::class);

            if (!$provider instanceof ClassListenerProviderInterface
                || !$compiler instanceof DiscoveryCompiler
            ) {
                throw new RuntimeException(sprintf(
                    'Discovery compilation requires %s and %s container services.',
                    ClassListenerProviderInterface::class,
                    DiscoveryCompiler::class,
                ));
            }

            $delta = self::mergeConfigDelta($delta, $compiler->compile(
                $provider->getClassListeners(),
                dirname($cache->config),
            ));
        }

        foreach ($this->compileContributors($config, $container, $classes) as $contribution) {
            $delta = self::mergeConfigDelta($delta, $contribution);
        }

        return [
            'delta' => $delta,
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
                $name = $class->fullyQualifiedName;

                if (!class_exists($name)) {
                    throw new RuntimeException(sprintf(
                        'Cannot compile generated resolver: discovered class "%s" is not loadable.',
                        $name,
                    ));
                }

                $entries[$name] = true;
            }
        }

        ksort($entries);

        return array_keys($entries);
    }

    private function hasDiscoveryWork(Config $config): bool
    {
        foreach ([
            ClassFinderConfigKey::LISTENERS,
            ClassFinderCompileConfigKey::LISTENER_COMPILERS,
            ConfigKey::COMPILE_CACHE_CONTRIBUTORS,
        ] as $key) {
            $entries = $config->get($key, []);

            if (is_array($entries) && $entries !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<class-string> $classes
     * @return list<array<string, mixed>>
     */
    private function compileContributors(
        Config $config,
        ContainerInterface $container,
        array $classes,
    ): array
    {
        $entries = $config->get(ConfigKey::COMPILE_CACHE_CONTRIBUTORS, []);

        if (!is_array($entries)) {
            throw new RuntimeException(sprintf('%s config value must be an array.', ConfigKey::COMPILE_CACHE_CONTRIBUTORS));
        }

        $contributions = [];

        foreach ($entries as $entry) {
            $contributor = is_string($entry) ? $container->get($entry) : $entry;

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

    /**
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     * @return array<string, mixed>
     */
    private static function mergeConfigDelta(array $base, array $override): array
    {
        return self::stringKeyedArray(
            config_merge($base, $override),
            'Compiled config delta',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new RuntimeException(sprintf(
                '%s must be an array; got %s.',
                $label,
                get_debug_type($value),
            ));
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new RuntimeException(sprintf(
                    '%s must contain only string root keys.',
                    $label,
                ));
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }
}
