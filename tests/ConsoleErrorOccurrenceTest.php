<?php

declare(strict_types=1);

use Componenta\App\Console\App;
use Componenta\App\Console\EventDispatcherFactory;
use Componenta\App\Console\InputFactoryInterface;
use Componenta\App\Console\IOFactory;
use Componenta\App\Console\OutputFactoryInterface;
use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Handler\CliErrorHandlerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('wraps a command failure in one error occurrence and returns a failing exit status', function (): void {
    $failure = new DomainException('Command failed.');
    $input = $this->createStub(InputFactoryInterface::class);
    $input->method('createInput')->willReturn(new ArrayInput(['command' => 'fixture:fail']));
    $output = $this->createStub(OutputFactoryInterface::class);
    $output->method('createOutput')->willReturn(new BufferedOutput());
    $errors = $this->createMock(CliErrorHandlerInterface::class);
    $errors->expects($this->once())->method('handle')->with(
        $this->callback(static fn (ErrorOccurrence $error): bool => $error->exception === $failure && $error->id !== ''),
        $this->isInstanceOf(CliErrorContextInterface::class),
    );
    $app = new App(new IOFactory($input, $output), new EventDispatcherFactory(), $errors);
    $command = new Command('fixture:fail');
    $command->setCode(static function () use ($failure): never { throw $failure; });
    $app->add($command);

    expect($app->run())->toBe(1);
});
