<?php

declare(strict_types=1);

use Componenta\App\Boot\Target\ConsoleBootTargetInterface;
use Componenta\App\Console\ConsoleCommandRegistry;
use Symfony\Component\Console\Command\Command;

final class RegistryTestConsoleTarget implements ConsoleBootTargetInterface
{
    /**
     * @var list<Command>
     */
    public array $added = [];

    public function add(Command $command): Command
    {
        $this->added[] = $command;

        return $command;
    }

    public function addCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->add($command);
        }
    }
}

final class RegistryTestFirstCommand extends Command
{
    public function __construct()
    {
        parent::__construct('registry:test');
    }
}

final class RegistryTestSecondCommand extends Command
{
    public function __construct()
    {
        parent::__construct('registry:test');
    }
}

describe('console command registry', function () {
    it('registers the same command class once', function () {
        $target = new RegistryTestConsoleTarget();
        $registry = new ConsoleCommandRegistry();
        $command = new RegistryTestFirstCommand();

        $first = $registry->register($target, $command);
        $second = $registry->register($target, new RegistryTestFirstCommand());

        expect($first)->toBe($command)
            ->and($second)->toBe($command)
            ->and($target->added)->toHaveCount(1)
            ->and($registry->hasClass(RegistryTestFirstCommand::class))->toBeTrue()
            ->and($registry->hasName('registry:test'))->toBeTrue()
            ->and($registry->toArray())->toBe([
                [
                    'name' => 'registry:test',
                    'class' => RegistryTestFirstCommand::class,
                ],
            ]);
    });

    it('rejects two command classes with the same command name', function () {
        $target = new RegistryTestConsoleTarget();
        $registry = new ConsoleCommandRegistry();

        $registry->register($target, new RegistryTestFirstCommand());

        expect(fn() => $registry->register($target, new RegistryTestSecondCommand()))
            ->toThrow(LogicException::class, 'Console command name "registry:test" is already registered');
    });
});
