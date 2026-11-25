<?php

declare(strict_types=1);

use Componenta\App\AppInterface;
use Componenta\App\Boot\ConsoleBootTargetAdapter;
use Componenta\App\Boot\Target\ConsoleBootTargetInterface;
use Componenta\App\Console\App;
use Componenta\App\Console\EventDispatcherFactory;
use Componenta\App\Console\IOFactory;
use Componenta\App\Scope;
use Componenta\Error\Handler\CliErrorHandlerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

it('registers executable commands through a separate console boot target without exposing the Componenta application', function (): void {
    $app = new App(
        new IOFactory(),
        new EventDispatcherFactory(),
        $this->createStub(CliErrorHandlerInterface::class),
    );
    $target = (new ConsoleBootTargetAdapter())->create($app, Scope::CLI);
    expect($target)->toBeInstanceOf(ConsoleBootTargetInterface::class)
        ->not->toBe($app)
        ->not->toBeInstanceOf(AppInterface::class)
        ->and(is_callable([$target, 'run']))->toBeFalse();

    $first = new Command('boot-target:first');
    $first->setCode(static fn (): int => 7);
    $second = new Command('boot-target:second');
    $second->setCode(static fn (): int => 9);

    $target->add($first);
    $target->addCommands([$second]);
    $tester = new ApplicationTester($app->console);

    expect($tester->run(['command' => 'boot-target:first']))->toBe(7)
        ->and($tester->run(['command' => 'boot-target:second']))->toBe(9);
});
