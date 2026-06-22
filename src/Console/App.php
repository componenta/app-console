<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Componenta\App\AppInterface;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Handler\CliErrorHandlerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Console application wrapping Symfony Console with Componenta error handling,
 * event dispatch, and DI integration.
 */
final class App implements AppInterface, EventListenerProviderInterface
{
    /**
     * Underlying Symfony Console Application
     */
    private(set) readonly Application $console;

    /** @var list<EventListenerInterface> */
    private array $listeners = [];

    /** @var list<EventSubscriberInterface> */
    private array $subscribers = [];

    public string $name {
        get => $this->console->getName();
        set => $this->console->setName($value);
    }

    public string $version {
        get => $this->console->getVersion();
        set => $this->console->setVersion($value);
    }

    public function __construct(
        private readonly IOFactory                       $ioFactory,
        private readonly EventDispatcherFactoryInterface $dispatcherFactory,
        private(set) readonly CliErrorHandlerInterface   $errorHandler,
        string                                           $name = 'Componenta',
        string                                           $version = '1.0.0',
    ) {
        $this->console = new Application($name, $version);
        $this->console->setAutoExit(false);
        $this->console->setCatchExceptions(false);
    }

    public function run(): ?int
    {
        $dispatcher = $this->dispatcherFactory->createDispatcher();

        foreach ($this->subscribers as $subscriber) {
            $dispatcher->addSubscriber($subscriber);
        }

        foreach ($this->listeners as $listener) {
            $dispatcher->addListener(
                $listener->getEventName(),
                $listener,
                $listener->getPriority(),
            );
        }

        $this->console->setDispatcher($dispatcher);

        $input = $this->ioFactory->createInput();
        $output = $this->ioFactory->createOutput();

        try {
            return $this->console->run($input, $output);
        } catch (\Throwable $e) {
            $this->errorHandler->handle($e, new CliContext($input, $output));

            return 1;
        }
    }

    public function add(Command $command): Command
    {
        return $this->console->addCommand($command);
    }

    /**
     * @param list<Command> $commands
     */
    public function addCommands(array $commands): void
    {
        $this->console->addCommands($commands);
    }

    public function addListener(EventListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function createFromContainer(ContainerInterface $container): self
    {
        return new self(
            ioFactory: $container->has(IOFactory::class)
                ? $container->get(IOFactory::class)
                : new IOFactory(),
            dispatcherFactory: $container->has(EventDispatcherFactoryInterface::class)
                ? $container->get(EventDispatcherFactoryInterface::class)
                : new EventDispatcherFactory(),
            errorHandler: $container->get(CliErrorHandlerInterface::class),
            name: $container->has('app.name')
                ? $container->get('app.name')
                : 'Componenta',
            version: $container->has('app.version')
                ? $container->get('app.version')
                : '1.0.0',
        );
    }
}
