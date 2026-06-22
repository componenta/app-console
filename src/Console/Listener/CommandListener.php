<?php

declare(strict_types=1);

namespace Componenta\App\Console\Listener;

use Closure;
use Componenta\App\Console\EventListenerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;


/**
 * Abstract listener for ConsoleEvents::COMMAND
 *
 * Triggered before a command is executed. Use cases include:
 * - Logging command invocation
 * - Validating input or environment
 * - Setting up resources
 * - Disabling command execution conditionally
 *
 * @example
 * ```php
 * class LogCommandListener extends CommandListener
 * {
 *     protected function supportsCommand(ConsoleCommandEvent $event): bool
 *     {
 *         // Only log 'app:' prefixed commands
 *         return str_starts_with($event->getCommand()?->getName() ?? '', 'app:');
 *     }
 *
 *     protected function handleCommand(ConsoleCommandEvent $event): void
 *     {
 *         $this->logger->info('Executing: ' . $event->getCommand()->getName());
 *     }
 * }
 * ```
 */
abstract class CommandListener extends AbstractEventListener
{
    /**
     * Create command listener from callable
     *
     * @param Closure(ConsoleCommandEvent): void $callback Handler callback
     * @param int $priority Listener priority
     * @return EventListenerInterface Listener instance
     */
    public static function fromCallable(
        Closure $callback,
        string $eventName = ConsoleEvents::COMMAND,
        int $priority = 0,
    ): EventListenerInterface {
        return new class($callback, $priority) extends CommandListener {
            public function __construct(
                private readonly Closure $callback,
                int $priority,
            ) {
                parent::__construct($priority);
            }

            protected function handleCommand(ConsoleCommandEvent $event): void
            {
                ($this->callback)($event);
            }
        };
    }

    /**
     * Get event name
     *
     * @return string ConsoleEvents::COMMAND
     */
    public function getEventName(): string
    {
        return ConsoleEvents::COMMAND;
    }

    /**
     * Check if listener supports the event
     *
     * Validates event type and delegates to supportsCommand().
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     * @return bool True if event is ConsoleCommandEvent and supportsCommand() returns true
     */
    public function supports(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): bool {
        return $event instanceof ConsoleCommandEvent && $this->supportsCommand($event);
    }

    /**
     * Check if listener supports the command event
     *
     * Override to filter specific commands or conditions.
     *
     * @param ConsoleCommandEvent $event Command event
     * @return bool True if command should be handled
     */
    protected function supportsCommand(ConsoleCommandEvent $event): bool
    {
        return true;
    }

    /**
     * Handle the event
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     */
    protected function handle(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): void {
        $this->handleCommand($event);
    }

    /**
     * Handle the command event
     *
     * Implement command-specific handling logic.
     *
     * @param ConsoleCommandEvent $event Command event
     */
    abstract protected function handleCommand(ConsoleCommandEvent $event): void;
}
