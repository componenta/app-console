<?php

declare(strict_types=1);

use Componenta\App\Config\AsConfig;
use Componenta\App\Config\ConfigFactory;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Stdlib\PathResolver;
use Symfony\Component\Console\Tester\CommandTester;

#[AsConfig]
final class PreparedConfigurationProviderFixture
{
    public static string $value = 'first';
    public static int $calls = 0;

    public function __invoke(): iterable
    {
        self::$calls++;
        yield ['order' => ['attribute'], 'current' => self::$value];
        yield ['order' => ['attribute-second']];
    }
}

#[AsConfig]
final class AddedConfigurationProviderFixture
{
    public function __invoke(): array
    {
        return ['order' => ['added']];
    }
}

it('builds discovery without changing providers and uses the prepared iterator for attribute configuration', function (): void {
    $root = sys_get_temp_dir() . '/componenta_prepared_config_' . bin2hex(random_bytes(6));
    mkdir($root . '/src', 0700, true);
    mkdir($root . '/config', 0700, true);
    file_put_contents($root . '/src/Provider.php', '<?php final class PreparedConfigurationProviderFixture {}');
    file_put_contents($root . '/config/one.php', '<?php return ["order" => ["file-one"]];');
    file_put_contents($root . '/config/two.php', '<?php return ["order" => ["file-two"]];');
    $source = <<<'PHP'
        <?php
        use Componenta\App\Config\AttributeConfigProvider as Attributes;
        use Componenta\App\Config\ConfigDefinition;
        use Componenta\App\Config\DiscoveryDefinition;
        use Componenta\Config\FileProvider;
        return new ConfigDefinition(
            providers: [
                new \Componenta\App\ConfigProvider(),
                new \Componenta\App\Console\ConfigProvider(),
                new Attributes(
                    // Keep the original registration in every mode.
                ),
                new FileProvider($paths->resolve('config/one.php')),
                static fn () => ['order' => ['between']],
                new FileProvider($paths->resolve('config/two.php')),
            ],
            discovery: new DiscoveryDefinition(['src']),
        );
        PHP;
    $configFile = $root . '/config/config.php';
    file_put_contents($configFile, $source);
    $paths = new PathResolver($root);
    $definition = static function () use ($paths, $configFile): mixed {
        return require $configFile;
    };
    $load = static fn () => ConfigFactory::create($paths, $definition, new Environment([]));

    try {
        PreparedConfigurationProviderFixture::$calls = 0;
        PreparedConfigurationProviderFixture::$value = 'first';
        $before = $load();
        expect($before->config->get('order'))->toBe(['attribute', 'attribute-second', 'file-one', 'between', 'file-two']);
        $container = (new ContainerFactory())->create($before->config, $before->dependencies);
        $command = new CommandTester($container->get(BuildCommand::class));
        expect($command->execute([]))->toBe(0);
        expect(PreparedConfigurationProviderFixture::$calls)->toBe(1);

        $builtSource = file_get_contents($configFile);
        expect($builtSource)->toBe($source);
        PreparedConfigurationProviderFixture::$value = 'runtime';
        $after = $load();
        expect($after->config->get('order'))->toBe(['attribute', 'attribute-second', 'file-one', 'between', 'file-two'])
            ->and($after->config->get('current'))->toBe('runtime')
            ->and(PreparedConfigurationProviderFixture::$calls)->toBe(2);

        $rebuild = (new ContainerFactory())->create($after->config, $after->dependencies);
        expect((new CommandTester($rebuild->get(BuildCommand::class)))->execute([]))->toBe(0)
            ->and(file_get_contents($configFile))->toBe($builtSource)
            ->and($load()->config->get('order'))->toBe(['attribute', 'attribute-second', 'file-one', 'between', 'file-two']);

        file_put_contents($root . '/src/Provider.php', '<?php final class PreparedConfigurationProviderFixture {} final class AddedConfigurationProviderFixture {}');
        expect($load()->config->get('order'))->toBe(['attribute', 'attribute-second', 'file-one', 'between', 'file-two']);
        unlink($root . '/var/cache/build/classes.php');
        $development = $load();
        expect($development->config->get('order'))->toBe(['attribute', 'attribute-second', 'added', 'file-one', 'between', 'file-two'])
            ->and(is_file($root . '/var/cache/build/classes.php'))->toBeFalse();

        $rebuild = (new ContainerFactory())->create($development->config, $development->dependencies);
        expect((new CommandTester($rebuild->get(BuildCommand::class)))->execute([]))->toBe(0)
            ->and($load()->config->get('order'))->toBe(['attribute', 'attribute-second', 'added', 'file-one', 'between', 'file-two']);
    } finally {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($root);
    }
});
