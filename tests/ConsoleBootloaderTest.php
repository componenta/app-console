<?php

declare(strict_types=1);

use Componenta\App\Boot\BootContext;
use Componenta\App\Boot\ConsoleBootloader;
use Componenta\App\Boot\Target\ConsoleBootTargetInterface;
use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
use Componenta\App\Console\ConsoleCommandRegistry;
use Componenta\App\Scope;
use Componenta\ClassFinder\ClassIterator;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\Tokenizer\ClassInfo;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

final class ConsoleBootloaderTestContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(private array $entries) {}

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new RuntimeException(sprintf('Missing entry: %s', $id));
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

final class ConsoleBootloaderTestTarget implements ConsoleBootTargetInterface
{
    /** @var list<Command> */
    public array $commands = [];

    public function add(Command $command): Command
    {
        $this->commands[] = $command;

        return $command;
    }

    public function addCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->add($command);
        }
    }
}

#[AsCommand(name: 'test:configured')]
final class ConsoleBootloaderConfiguredCommand extends Command
{
}

#[AsCommand(name: 'test:discovered')]
final class ConsoleBootloaderDiscoveredCommand extends Command
{
}

#[AsCommand(
    name: 'test:metadata',
    description: 'Metadata description',
    aliases: ['test:m'],
    hidden: true,
    help: 'Metadata help',
    usages: ['--example'],
)]
final class ConsoleBootloaderMetadataCommand extends Command {}

#[AsCommand(name: 'test:attribute', usages: ['--example'])]
final class ConsoleBootloaderExplicitNameCommand extends Command
{
    public function __construct()
    {
        parent::__construct('test:explicit');
    }
}

it('registers configured commands and keeps development AsCommand discovery', function (): void {
    $registry = new ConsoleCommandRegistry();
    $bootloader = new ConsoleBootloader($registry);
    $target = new ConsoleBootloaderTestTarget();
    $iterator = new ClassIterator([
        __FILE__ => new ClassInfo(ConsoleBootloaderConfiguredCommand::class),
        __FILE__ . ':discovered' => new ClassInfo(ConsoleBootloaderDiscoveredCommand::class),
    ]);
    $container = new ConsoleBootloaderTestContainer([
        ConsoleBootloaderConfiguredCommand::class => new ConsoleBootloaderConfiguredCommand(),
        ConsoleBootloaderDiscoveredCommand::class => new ConsoleBootloaderDiscoveredCommand(),
        ClassIteratorInterface::class => $iterator,
    ]);

    $bootloader->boot(new BootContext(
        new ContainerValue(
            $container,
            new Config([
                ConsoleConfigKey::COMMANDS => [
                    ConsoleBootloaderConfiguredCommand::class,
                ],
            ], new Environment(['APP_ENV' => 'development'])),
        ),
        Scope::CLI,
        $target,
    ));

    expect(array_map(
        static fn(Command $command): ?string => $command->getName(),
        $target->commands,
    ))->toBe([
        'test:configured',
        'test:discovered',
    ]);
});

it('keeps Symfony AsCommand semantics identical in development and production', function (): void {
    $boot = static function (string $environment): array {
        $classes = [
            ConsoleBootloaderMetadataCommand::class,
            ConsoleBootloaderExplicitNameCommand::class,
        ];
        $entries = [];

        foreach ($classes as $class) {
            $entries[$class] = new $class();
        }

        $configured = $classes;
        if ($environment === 'development') {
            $configured = [];
            $entries[ClassIteratorInterface::class] = new ClassIterator([
                __FILE__ . ':metadata' => new ClassInfo(ConsoleBootloaderMetadataCommand::class),
                __FILE__ . ':explicit' => new ClassInfo(ConsoleBootloaderExplicitNameCommand::class),
            ]);
        }

        $target = new ConsoleBootloaderTestTarget();
        $bootloader = new ConsoleBootloader(new ConsoleCommandRegistry());
        $bootloader->boot(new BootContext(
            new ContainerValue(
                new ConsoleBootloaderTestContainer($entries),
                new Config([
                    ConsoleConfigKey::COMMANDS => $configured,
                ], new Environment(['APP_ENV' => $environment])),
            ),
            Scope::CLI,
            $target,
        ));

        return $target->commands;
    };

    $snapshot = static fn (Command $command): array => [
        'name' => $command->getName(),
        'aliases' => $command->getAliases(),
        'hidden' => $command->isHidden(),
        'description' => $command->getDescription(),
        'help' => $command->getHelp(),
        'usages' => $command->getUsages(),
    ];
    $development = array_map($snapshot, $boot('development'));
    $production = array_map($snapshot, $boot('production'));

    expect($development)->toBe($production)
        ->and($development)->toBe([
            [
                'name' => 'test:metadata',
                'aliases' => ['test:m'],
                'hidden' => true,
                'description' => 'Metadata description',
                'help' => 'Metadata help',
                'usages' => ['test:metadata --example'],
            ],
            [
                'name' => 'test:explicit',
                'aliases' => [],
                'hidden' => false,
                'description' => '',
                'help' => '',
                'usages' => ['test:explicit --example'],
            ],
        ]);
});
