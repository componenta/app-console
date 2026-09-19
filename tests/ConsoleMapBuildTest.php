<?php

declare(strict_types=1);

use Componenta\App\Boot\BootContext;
use Componenta\App\Boot\ConsoleBootloader;
use Componenta\App\Boot\Target\ConsoleBootTargetInterface;
use Componenta\App\Config\ConfigDefinition;
use Componenta\App\Config\ConfigFactory;
use Componenta\App\Config\DiscoveryDefinition;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Scope;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Stdlib\PathResolver;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[AsCommand(name: 'mapped:example', description: 'attribute description', aliases: ['mapped:alias'], hidden: true, help: 'attribute help', usages: ['example'])]
final class MappedConsoleExample extends Command
{
    public static int $constructions = 0;
    protected function configure(): void
    {
        ++self::$constructions;
        $this->setName('constructor:name');
        $this->setDescription('constructor description');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write('executed');
        return 7;
    }
}

it('builds console discovery lazily and preserves command metadata and execution through real DI', function (): void {
    $root = sys_get_temp_dir() . '/componenta_console_map_' . bin2hex(random_bytes(8));
    mkdir($root . '/src', 0700, true);
    file_put_contents($root . '/src/Example.php', '<?php final class MappedConsoleExample {}');
    $load = static function () use ($root) {
        $result = ConfigFactory::create(new PathResolver($root), new ConfigDefinition(
            providers: [new \Componenta\App\ConfigProvider(), new \Componenta\App\Console\ConfigProvider()],
            discovery: new DiscoveryDefinition(['src']),
        ), new Environment([]));
        return (new ContainerFactory())->create($result->config, $result->dependencies);
    };
    $run = static function ($container): array {
        $target = new class implements ConsoleBootTargetInterface {
            public Application $app;
            public function __construct() { $this->app = new Application(); }
            public function add(Command $command): Command { return $this->app->addCommand($command); }
            public function addCommands(array $commands): void { $this->app->addCommands($commands); }
        };
        $container->get(ConsoleBootloader::class)->boot(new BootContext($container, Scope::CLI, $target));
        $command = $target->app->get('mapped:example');
        expect($target->app->get('mapped:alias'))->toBe($command);
        $tester = new CommandTester($command);
        return [$command->getName(), $command->getDescription(), $command->getHelp(), $command->getUsages(), $command->isHidden(), $tester->execute([]), $tester->getDisplay()];
    };
    try {
        $expected = ['mapped:example', 'attribute description', 'attribute help', ['mapped:example example'], true, 7, 'executed'];
        expect($run($load()))->toBe($expected);
        MappedConsoleExample::$constructions = 0;
        $container = $load();
        $build = new CommandTester($container->get(BuildCommand::class));
        expect(MappedConsoleExample::$constructions)->toBe(0);
        expect($build->execute([]))->toBe(0)
            ->and(MappedConsoleExample::$constructions)->toBe(0)
            ->and(is_file($root . '/var/cache/build/commands.php'))->toBeTrue();
        expect($run($load()))->toBe($expected)->and($run($load()))->toBe($expected);
        foreach ([
            '<?php return false;',
            '<?php return ["MappedConsoleExample" => "no"];',
            '<?php return [;',
            '<?php throw new LogicException("Invalid map");',
        ] as $invalidMap) {
            file_put_contents($root . '/var/cache/build/commands.php', $invalidMap);
            expect($run($load()))->toBe($expected);
        }
        file_put_contents($root . '/var/cache/build/commands.php', '<?php return ["MappedConsoleExample" => false];');
        unlink($root . '/var/cache/build/classes.php');
        expect($run($load()))->toBe($expected);
        expect((new CommandTester($load()->get(BuildCommand::class)))->execute([]))->toBe(0);
        expect($run($load()))->toBe($expected);
    } finally {
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($entries as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
        rmdir($root);
    }
});
