<?php

declare(strict_types=1);

use Componenta\App\Console\Compile\ConsoleCommandMapContributor;
use Componenta\App\Console\ConfigKey;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'test:compiled:first')]
final class CompiledFirstConsoleCommand extends Command {}

#[AsCommand(name: 'test:compiled:second')]
final class CompiledSecondConsoleCommand extends Command {}

final class NotACompiledConsoleCommand extends Command {}

it('compiles AsCommand classes into production console configuration', function (): void {
    $delta = (new ConsoleCommandMapContributor())->compile([
        CompiledSecondConsoleCommand::class,
        NotACompiledConsoleCommand::class,
        CompiledFirstConsoleCommand::class,
        CompiledFirstConsoleCommand::class,
    ]);

    expect($delta)->toBe([
        ConfigKey::COMMANDS => [
            CompiledFirstConsoleCommand::class,
            CompiledSecondConsoleCommand::class,
        ],
    ]);
});

it('omits the console command section when no commands are discovered', function (): void {
    expect((new ConsoleCommandMapContributor())->compile([]))->toBe([]);
});
