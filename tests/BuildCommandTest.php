<?php

declare(strict_types=1);

use Componenta\App\Build\BuildStore;
use Componenta\App\Console\Build\BuildConsole;
use Componenta\Stdlib\PathResolver;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function buildConsoleFixture(): string
{
    $root = sys_get_temp_dir() . '/componenta_build_console_' . bin2hex(random_bytes(8));
    mkdir($root . '/config', 0700, true);
    file_put_contents($root . '/config/config.php', <<<'PHP'
        <?php
        use Componenta\App\Build\ApplicationBuilderInterface;
        use Componenta\App\Build\BuildContext;
        use Componenta\App\Config\ConfigDefinition;
        use Componenta\App\ConfigKey;

        file_put_contents($paths->resolve('providers-invoked.txt'), 'invoked', FILE_APPEND);
        return new ConfigDefinition(providers: [
            new \Componenta\App\ConfigProvider(),
            static fn () => [
                ConfigKey::BUILDERS => ['fixture.builder'],
                ConfigKey::BOOTLOADERS => ['missing.runtime.bootloader'],
                ConfigKey::DEPENDENCIES => [
                    ConfigKey::FACTORIES => [
                        'fixture.builder' => static fn () => new class($paths->baseDir) implements ApplicationBuilderInterface {
                            public function __construct(private string $root) {}
                            public function build(BuildContext $build): void
                            {
                                if (is_file($this->root . '/fail.txt')) {
                                    throw new DomainException('Fixture compiler failed.');
                                }
                                $build->write('fixture.data', ['value' => file_exists($this->root . '/next.txt') ? 'next' : 'first']);
                                $build->skip('fixture.optional', 'Dynamic definition requires source.');
                            }
                        },
                    ],
                ],
            ],
        ]);
        PHP);

    return $root;
}

function removeBuildConsoleFixture(string $root): void
{
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($root);
}

it('dispatches ordinary commands without loading build configuration', function (): void {
    $root = buildConsoleFixture();
    try {
        expect(BuildConsole::run(new PathResolver($root), new ArrayInput(['command' => 'list'])))->toBeNull()
            ->and(file_exists($root . '/providers-invoked.txt'))->toBeFalse();
    } finally {
        removeBuildConsoleFixture($root);
    }
});

it('shows build help and version without invoking configuration or creating artifacts', function (array $arguments): void {
    $root = buildConsoleFixture();
    try {
        $output = new BufferedOutput();
        expect(BuildConsole::run(new PathResolver($root), new ArrayInput($arguments), $output))->toBe(0)
            ->and(file_exists($root . '/providers-invoked.txt'))->toBeFalse()
            ->and(is_dir($root . '/var'))->toBeFalse();
    } finally {
        removeBuildConsoleFixture($root);
    }
})->with([
    [['command' => 'app:build', '--help' => true]],
    [['command' => 'app:build', '--version' => true]],
    [['command' => 'app:clean', '--help' => true]],
    [['command' => 'app:clean', '--version' => true]],
]);

it('builds from fresh source without booting the runtime and reports unsupported optional artifacts', function (): void {
    $root = buildConsoleFixture();
    try {
        $output = new BufferedOutput();
        $paths = new PathResolver($root);
        expect(BuildConsole::run($paths, new ArrayInput(['command' => 'app:build']), $output))->toBe(0);
        $first = (new BuildStore($root . '/var/cache/build'))->load();
        expect($first?->read('fixture.data'))->toBe(['value' => 'first'])
            ->and(file_get_contents($root . '/providers-invoked.txt'))->toBe('invoked')
            ->and($output->fetch())->toContain('fixture.optional', 'Dynamic definition', 'Application build completed.');

        touch($root . '/next.txt');
        expect(BuildConsole::run($paths, new ArrayInput(['command' => 'app:build']), $output))->toBe(0);
        $second = (new BuildStore($root . '/var/cache/build'))->load();
        expect($second?->generation)->not->toBe($first->generation)
            ->and($second?->read('fixture.data'))->toBe(['value' => 'next'])
            ->and($first->read('fixture.data'))->toBe(['value' => 'first']);
    } finally {
        removeBuildConsoleFixture($root);
    }
});

it('keeps the previous generation when a builder fails and returns a nonzero status', function (): void {
    $root = buildConsoleFixture();
    try {
        $paths = new PathResolver($root);
        $output = new BufferedOutput();
        expect(BuildConsole::run($paths, new ArrayInput(['command' => 'app:build']), $output))->toBe(0);
        $before = (new BuildStore($root . '/var/cache/build'))->load();
        $output->fetch();
        touch($root . '/fail.txt');

        expect(BuildConsole::run($paths, new ArrayInput(['command' => 'app:build']), $output))->not->toBe(0)
            ->and($output->fetch())->toContain('Fixture compiler failed.')
            ->and((new BuildStore($root . '/var/cache/build'))->load()?->generation)->toBe($before->generation);
    } finally {
        removeBuildConsoleFixture($root);
    }
});

it('unpublishes without evaluating configuration and preserves a pinned generation', function (): void {
    $root = buildConsoleFixture();
    try {
        $paths = new PathResolver($root);
        $output = new BufferedOutput();
        expect(BuildConsole::run($paths, new ArrayInput(['command' => 'app:build']), $output))->toBe(0);
        $prepared = (new BuildStore($root . '/var/cache/build'))->load();
        file_put_contents($root . '/config/config.php', '<?php throw new LogicException("Clean must not bootstrap providers.");');
        foreach (range(1, 2) as $_) {
            expect(BuildConsole::run($paths, new ArrayInput(['command' => 'app:clean']), $output))->toBe(0);
        }
        expect((new BuildStore($root . '/var/cache/build'))->load())->toBeNull()
            ->and($prepared->read('fixture.data'))->toBe(['value' => 'first']);
    } finally {
        removeBuildConsoleFixture($root);
    }
});

it('preserves an explicitly supplied environment when a custom console host invokes the build factory', function (): void {
    $root = buildConsoleFixture();
    try {
        $paths = new PathResolver($root);
        $environment = new \Componenta\Config\Environment(['APP_BUILD_ID' => 'custom-console-release']);
        $composition = \Componenta\App\Config\ConfigFactory::create(
            $paths,
            static fn () => require $paths->resolve('config/config.php'),
            $environment,
        );
        $container = (new \Componenta\DI\ContainerFactory())->create($composition->config, $composition->dependencies);
        $command = (new \Componenta\App\Console\Command\BuildCommandFactory())($container);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);

        expect($tester->execute([]))->toBe(0)
            ->and((new BuildStore($root . '/var/cache/build', 'custom-console-release'))->load()?->read('fixture.data'))->toBe(['value' => 'first']);
    } finally {
        removeBuildConsoleFixture($root);
    }
});
