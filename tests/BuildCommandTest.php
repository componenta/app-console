<?php

declare(strict_types=1);

use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\Config\Config;
use Componenta\Config\Environment;
use Componenta\Stdlib\PathResolverInterface;
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
        ->toThrow(RuntimeException::class, 'app:build requires APP_ENV=development or the process-local COMPONENTA_BUILD=1 flag.');
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

it('uses content-addressed config shards across repeated builds in one process', function (): void {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'componenta-build-' . bin2hex(random_bytes(8));
    $paths = new BuildCommandTestPathResolver($root);

    try {
        foreach (['first', 'second'] as $value) {
            $command = new BuildCommand(
                new Config([
                    'large_config' => ['payload' => str_repeat($value, 17_000)],
                ], new Environment(['APP_ENV' => 'development'])),
                $paths,
                new BuildCommandTestContainer(),
            );

            (new CommandTester($command))->execute([]);
            $loaded = \Componenta\Config\ConfigLoader::loadFromFile(
                $root . DIRECTORY_SEPARATOR . 'var/cache/build/config.cache.php',
            );

            expect($loaded->get(new \Componenta\Config\ConfigPath('large_config.payload')))->toBe(str_repeat($value, 17_000));
        }
    } finally {
        if (is_dir($root)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($root);
        }
    }
});
