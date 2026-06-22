<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logging subscriber for command execution tracking
 *
 * Logs command lifecycle events:
 * - Command start (info): command name, arguments, options
 * - Command finish (info): command name, exit code
 * - Command error (error): exception details
 *
 * Useful for audit trails, debugging, and monitoring.
 *
 * @example
 * ```php
 * $app->addSubscriber(new LoggingSubscriber($logger));
 * ```
 */
final readonly class LoggingSubscriber implements EventSubscriberInterface
{
    /**
     * Create logging subscriber
     *
     * @param LoggerInterface $logger PSR-3 logger instance
     */
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * Get subscribed events
     *
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand', 100],
            ConsoleEvents::TERMINATE => ['onTerminate', -100],
            ConsoleEvents::ERROR => ['onError', 100],
        ];
    }

    /**
     * Log command start
     *
     * @param ConsoleCommandEvent $event Command event
     */
    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        $this->logger->info('Command started', [
            'command' => $command?->getName(),
            'arguments' => $event->getInput()->getArguments(),
            'options' => $event->getInput()->getOptions(),
        ]);
    }

    /**
     * Log command completion
     *
     * @param ConsoleTerminateEvent $event Terminate event
     */
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();

        $this->logger->info('Command finished', [
            'command' => $command?->getName(),
            'exit_code' => $event->getExitCode(),
        ]);
    }

    /**
     * Log command error
     *
     * @param ConsoleErrorEvent $event Error event
     */
    public function onError(ConsoleErrorEvent $event): void
    {
        $error = $event->getError();

        $this->logger->error('Command failed', [
            'command' => $event->getCommand()?->getName(),
            'exception' => $error::class,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
    }
}
