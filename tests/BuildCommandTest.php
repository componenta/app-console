<?php

declare(strict_types=1);

use Componenta\App\Cache\CacheLayout;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Config\ConfigFactoryResult;
use Componenta\App\ConfigProvider as AppConfigProvider;
use Componenta\App\ContainerCacheMode;
use Componenta\App\ContainerFactory;
use Componenta\App\ContainerFactoryOptions;
use Componenta\App\Discovery\ListenerRestorer;
use Componenta\ClassFinder\ClassIterator;
use Componenta\ClassFinder\ClassListenerInterface;
use Componenta\ClassFinder\Compile\ConfigKey as ClassFinderCompileConfigKey;
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\ClassFinder\ConfigProvider as ClassFinderConfigProvider;
use Componenta\Config\Config;
use Componenta\Config\ConfigLoader;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\Autowire;
use Componenta\DI\Compile\Factory\CompiledFactoryDefinition;
use Componenta\DI\Compile\Factory\CompiledFactoryShardCompiler;
use Componenta\DI\ConfigKey as DiConfigKey;
use Componenta\DI\ContainerBuilder;
use Componenta\Stdlib\PathResolverInterface;
use Componenta\Tokenizer\ClassInfo;
use Symfony\Component\Console\Tester\CommandTester;

final readonly class BuildCommandCompiledDependency {}
#[Autowire]
final readonly class BuildCommandCompiledTarget { public function __construct(public BuildCommandCompiledDependency $dependency) {} }
final class BuildCommandRuntimeListener implements ClassListenerInterface { public function handle(ClassInfo $info): void {} }
final class BuildCommandTestPathResolver implements PathResolverInterface
{
    public string $baseDir { get => $this->root; }
    public function __construct(private string $root) {}
    public function resolve(string $path): string
    {
        if (preg_match('/^[A-Z]:[\\\\\/]/i', $path) === 1 || str_starts_with($path, '/')) { return $path; }
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
function removeBuildCommandCache(CacheLayout $cache, string $root): void
{
    foreach (glob($cache->buildDir . '/*') ?: [] as $file) { if (is_file($file)) { @unlink($file); } }
    foreach ([$cache->buildDir, dirname($cache->buildDir), dirname(dirname($cache->buildDir)), $root] as $directory) { if (is_dir($directory)) { @rmdir($directory); } }
}

it('refuses to build from non-development environment', function (): void {
    $command = new BuildCommand(new Config([], new Environment(['APP_ENV' => 'production'])), new BuildCommandTestPathResolver(sys_get_temp_dir()));
    expect(fn () => (new CommandTester($command))->execute([]))->toThrow(RuntimeException::class, 'app:build must run with APP_ENV=development');
});

it('fails clearly when discovery work is configured without class iterator', function (): void {
    $config = new Config([Componenta\App\ConfigKey::AUTOWIRE_ENTRY_CONTRIBUTORS => [new class implements Componenta\DI\Compile\Autowire\AutowireEntryContributorInterface { public function entries(): iterable { return []; } }]], new Environment(['APP_ENV' => 'development']));
    $command = new BuildCommand($config, new BuildCommandTestPathResolver(sys_get_temp_dir()), static fn (): ConfigFactoryResult => new ConfigFactoryResult($config));
    expect(fn () => (new CommandTester($command))->execute([]))->toThrow(RuntimeException::class, 'Cannot build discovery cache');
});

it('builds factory shards without persisting secrets and restores the caller umask', function (): void {
    $root = str_replace(DIRECTORY_SEPARATOR, '/', sys_get_temp_dir()) . '/componenta_app_console_build_' . bin2hex(random_bytes(4));
    $paths = new BuildCommandTestPathResolver($root);
    $environment = new Environment(['APP_ENV' => 'development', 'BUILD_ONLY_SECRET' => 'must-not-be-persisted']);
    $config = ConfigLoader::load($environment, new ClassFinderConfigProvider(), new AppConfigProvider());
    $iterator = new ClassIterator([
        __FILE__ . '#dependency' => new ClassInfo(BuildCommandCompiledDependency::class),
        __FILE__ . '#target' => new ClassInfo(BuildCommandCompiledTarget::class),
    ]);
    $command = new BuildCommand($config, $paths, static fn (): ConfigFactoryResult => new ConfigFactoryResult($config, $iterator));
    $originalUmask = umask(0o027);
    try {
        expect((new CommandTester($command))->execute([]))->toBe(0);
        $observedUmask = umask(0o027);
        $cache = CacheLayout::fromConfig($config, $paths);
        $containerCache = require $cache->container;
        $dependencies = $containerCache[DiConfigKey::DEPENDENCIES];
        $factories = $dependencies[DiConfigKey::FACTORIES];
        $targetFactory = CompiledFactoryDefinition::decode($factories[BuildCommandCompiledTarget::class]);
        $dependencyFactory = CompiledFactoryDefinition::decode($factories[BuildCommandCompiledDependency::class]);
        $cachedConfig = ConfigLoader::loadFromFile($cache->config);
        $configSource = file_get_contents($cache->config);
        expect($observedUmask)->toBe(0o027)
            ->and($containerCache)->not->toHaveKey(ContainerBuilder::CACHE_VALIDATED_KEY)
            ->and($targetFactory)->toBeInstanceOf(CompiledFactoryDefinition::class)
            ->and($dependencyFactory)->toBeInstanceOf(CompiledFactoryDefinition::class)
            ->and($cache->build($targetFactory->file))->toBeFile()
            ->and($cache->build($dependencyFactory->file))->toBeFile()
            ->and(glob($cache->buildDir . '/' . CompiledFactoryShardCompiler::FILE_PREFIX . '*.php'))->not->toBeEmpty()
            ->and(is_file($cache->build('container.resolver.php')))->toBeFalse()
            ->and(fileperms($cache->config) & 0o777)->toBe(0o600)
            ->and(fileperms($cache->container) & 0o777)->toBe(0o600)
            ->and($cachedConfig->environment)->toBeNull()
            ->and($configSource)->not->toContain('BUILD_ONLY_SECRET')->not->toContain('must-not-be-persisted');

        expect($cachedConfig->has(Componenta\App\ConfigKey::AUTOWIRE_ENTRY_CONTRIBUTORS))->toBeFalse()
            ->and($cachedConfig->has(Componenta\App\ConfigKey::COMPILE_CACHE_CONTRIBUTORS))->toBeFalse()
            ->and($cachedConfig->has(ClassFinderCompileConfigKey::LISTENER_COMPILERS))->toBeFalse()
            ->and($cachedConfig->has(ClassFinderConfigKey::LISTENERS))->toBeFalse()
            ->and($cachedConfig->has(ListenerRestorer::CACHE_KEY))->toBeFalse();

        $container = ContainerFactory::create($paths, $cachedConfig, options: new ContainerFactoryOptions(ContainerCacheMode::RequireCache));
        expect($container->get(BuildCommandCompiledTarget::class))->toBeInstanceOf(BuildCommandCompiledTarget::class)->dependency->toBeInstanceOf(BuildCommandCompiledDependency::class);

        $staleShard = $cache->build(CompiledFactoryShardCompiler::FILE_PREFIX . str_repeat('0', 32) . '.php');
        file_put_contents($staleShard, '<?php return null;');
        expect((new CommandTester($command))->execute([]))->toBe(0)->and($staleShard)->not->toBeFile();
    } finally {
        umask($originalUmask);
        removeBuildCommandCache(CacheLayout::fromConfig($config, $paths), $root);
    }
});

it('keeps regular runtime listeners and their targeted production discovery cache', function (): void {
    $root = str_replace(DIRECTORY_SEPARATOR, '/', sys_get_temp_dir()) . '/componenta_app_console_runtime_listener_' . bin2hex(random_bytes(4));
    $paths = new BuildCommandTestPathResolver($root);
    $environment = new Environment(['APP_ENV' => 'development']);
    $baseConfig = ConfigLoader::load($environment, new ClassFinderConfigProvider(), new AppConfigProvider());
    $data = $baseConfig->toArray(); $data[ClassFinderConfigKey::LISTENERS][] = BuildCommandRuntimeListener::class;
    $config = new Config($data, $environment);
    $iterator = new ClassIterator([__FILE__ . '#runtime-listener-target' => new ClassInfo(stdClass::class)]);
    $command = new BuildCommand($config, $paths, static fn (): ConfigFactoryResult => new ConfigFactoryResult($config, $iterator));
    try {
        expect((new CommandTester($command))->execute([]))->toBe(0);
        $productionConfig = ConfigLoader::loadFromFile(CacheLayout::fromConfig($config, $paths)->config);
        expect($productionConfig->get(ClassFinderConfigKey::LISTENERS))->toBe([BuildCommandRuntimeListener::class])
            ->and($productionConfig->get(ListenerRestorer::CACHE_KEY)['classes'])->toBe([stdClass::class]);
    } finally { removeBuildCommandCache(CacheLayout::fromConfig($config, $paths), $root); }
});

it('omits empty discovery metadata and factory sections', function (): void {
    $root = str_replace(DIRECTORY_SEPARATOR, '/', sys_get_temp_dir()) . '/componenta_app_console_empty_build_' . bin2hex(random_bytes(4));
    $paths = new BuildCommandTestPathResolver($root);
    $environment = new Environment(['APP_ENV' => 'development']);
    $sourceConfig = new Config([], $environment);
    $runtimeConfig = new Config([ListenerRestorer::CACHE_KEY => ['classes' => [stdClass::class], 'targets' => []], 'stale.compiled' => true], $environment);
    $command = new BuildCommand($runtimeConfig, $paths, static fn (): ConfigFactoryResult => new ConfigFactoryResult($sourceConfig, new ClassIterator([])));
    try {
        expect((new CommandTester($command))->execute([]))->toBe(0);
        $cache = CacheLayout::fromConfig($sourceConfig, $paths);
        $compiledConfig = ConfigLoader::loadFromFile($cache->config)->toArray();
        $containerCache = require $cache->container;
        expect($compiledConfig)->not->toHaveKey(ListenerRestorer::CACHE_KEY)->not->toHaveKey('stale.compiled')
            ->and($containerCache[DiConfigKey::DEPENDENCIES])->not->toHaveKey(DiConfigKey::FACTORIES);
    } finally { removeBuildCommandCache(CacheLayout::fromConfig($sourceConfig, $paths), $root); }
});
