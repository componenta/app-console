<?php

declare(strict_types=1);

use Componenta\App\Cache\CacheLayout;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\ContainerCacheMode;
use Componenta\App\ContainerFactory;
use Componenta\App\ContainerFactoryOptions;
use Componenta\App\Discovery\ListenerCompiler;
use Componenta\App\Discovery\ListenerRestorer;
use Componenta\ClassFinder\ClassIterator;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\ClassFinder\ClassListenerProviderInterface;
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\Config\Config;
use Componenta\Config\ConfigLoader;
use Componenta\Config\Environment;
use Componenta\DI\ConfigKey as DiConfigKey;
use Componenta\Stdlib\PathResolverInterface;
use Componenta\Tokenizer\ClassInfo;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class BuildCommandTestContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(private readonly array $entries = []) {}

    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? throw new RuntimeException($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

final class BuildCommandTestListenerProvider implements ClassListenerProviderInterface
{
    public function getClassListeners(): iterable
    {
        return [];
    }
}

abstract class BuildCommandGeneratedAbstract {}

final readonly class BuildCommandGeneratedDependency {}

final readonly class BuildCommandGeneratedTarget
{
    public function __construct(
        public BuildCommandGeneratedDependency $dependency,
    ) {}
}

final class BuildCommandTestPathResolver implements PathResolverInterface
{
    public string $baseDir {
        get => $this->root;
    }

    public function __construct(
        private string $root,
    ) {}

    public function resolve(string $path): string
    {
        if (preg_match('/^[A-Z]:[\\\\\/]/i', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}

it('refuses to build from non-development environment', function (): void {
    $command = new BuildCommand(
        new Config([], new Environment(['APP_ENV' => 'production'])),
        new BuildCommandTestPathResolver(sys_get_temp_dir()),
        new BuildCommandTestContainer(),
    );

    expect(fn() => (new CommandTester($command))->execute([]))
        ->toThrow(RuntimeException::class, 'app:build must run with APP_ENV=development');
});

it('fails clearly when discovery work is configured without class iterator', function (): void {
    $command = new BuildCommand(
        new Config([
            ClassFinderConfigKey::LISTENERS => [
                stdClass::class,
            ],
            AppConfigKey::COMPILE_CACHE_CONTRIBUTORS => [
                stdClass::class,
            ],
        ], new Environment(['APP_ENV' => 'development'])),
        new BuildCommandTestPathResolver(sys_get_temp_dir()),
        new BuildCommandTestContainer(),
    );

    expect(fn() => (new CommandTester($command))->execute([]))
        ->toThrow(RuntimeException::class, 'Cannot build discovery cache');
});

it('builds and activates a generated DI resolver from discovered concrete classes', function (): void {
    $root = str_replace(DIRECTORY_SEPARATOR, '/', sys_get_temp_dir())
        . '/componenta_app_console_build_'
        . bin2hex(random_bytes(4));
    $paths = new BuildCommandTestPathResolver($root);
    $iterator = new ClassIterator([
        __FILE__ . '#abstract' => new ClassInfo(BuildCommandGeneratedAbstract::class, isAbstract: true),
        __FILE__ . '#dependency' => new ClassInfo(BuildCommandGeneratedDependency::class),
        __FILE__ . '#target' => new ClassInfo(BuildCommandGeneratedTarget::class),
    ]);
    $config = new Config([], new Environment(['APP_ENV' => 'development']));
    $command = new BuildCommand(
        $config,
        $paths,
        new BuildCommandTestContainer([
            ClassIteratorInterface::class => $iterator,
            ListenerCompiler::class => new ListenerCompiler(new BuildCommandTestListenerProvider()),
        ]),
    );

    try {
        $status = (new CommandTester($command))->execute([]);
        $cache = CacheLayout::fromConfig($config, $paths);
        $containerCache = require $cache->container;
        $dependencies = $containerCache[DiConfigKey::DEPENDENCIES];

        expect($status)->toBe(0)
            ->and($cache->containerResolver)->toBeFile()
            ->and($dependencies[DiConfigKey::GENERATED_ENTRY_RESOLVER_FILE])
                ->toBe(CacheLayout::CONTAINER_RESOLVER)
            ->and($dependencies[DiConfigKey::GENERATED_ENTRY_RESOLVER_RELEASE_FINGERPRINT])
                ->toBeString()
                ->not->toBeEmpty()
            ->and(file_get_contents($cache->containerResolver))
                ->toContain(BuildCommandGeneratedTarget::class)
                ->not->toContain(BuildCommandGeneratedAbstract::class);

        $container = ContainerFactory::create(
            $paths,
            ConfigLoader::loadFromFile($cache->config),
            options: new ContainerFactoryOptions(ContainerCacheMode::RequireCache),
        );

        expect($container->get(BuildCommandGeneratedTarget::class))
            ->toBeInstanceOf(BuildCommandGeneratedTarget::class)
            ->dependency->toBeInstanceOf(BuildCommandGeneratedDependency::class);
    } finally {
        $cache = CacheLayout::fromConfig($config, $paths);

        foreach ([$cache->containerResolver, $cache->container, $cache->container . '.lock', $cache->config] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ([$cache->buildDir, dirname($cache->buildDir), dirname(dirname($cache->buildDir)), $root] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
});

it('omits empty discovery metadata from the generated config cache', function (): void {
    $root = str_replace(DIRECTORY_SEPARATOR, '/', sys_get_temp_dir())
        . '/componenta_app_console_empty_build_'
        . bin2hex(random_bytes(4));
    $paths = new BuildCommandTestPathResolver($root);
    $iterator = new ClassIterator([]);
    $config = new Config([], new Environment(['APP_ENV' => 'development']));
    $command = new BuildCommand(
        $config,
        $paths,
        new BuildCommandTestContainer([
            ClassIteratorInterface::class => $iterator,
            ListenerCompiler::class => new ListenerCompiler(new BuildCommandTestListenerProvider()),
        ]),
    );

    try {
        $status = (new CommandTester($command))->execute([]);
        $cache = CacheLayout::fromConfig($config, $paths);
        $compiledConfig = ConfigLoader::loadFromFile($cache->config)->toArray();

        expect($status)->toBe(0)
            ->and(is_file($cache->containerResolver))->toBeFalse()
            ->and($compiledConfig)->not->toHaveKey(ListenerRestorer::CACHE_KEY);
    } finally {
        $cache = CacheLayout::fromConfig($config, $paths);

        foreach ([$cache->containerResolver, $cache->container, $cache->container . '.lock', $cache->config] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ([$cache->buildDir, dirname($cache->buildDir), dirname(dirname($cache->buildDir)), $root] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
});
