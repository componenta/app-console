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
 * Abstract listener for ConsoleEvents::TERMINATE
 *
 * Triggered after command execution completes (success or failure).
 * Always fired after COMMAND and optionally after ERROR events.
 * Use cases include:
 * - Resource cleanup
 * - Profiling and timing output
 * - Logging execution results
 * - Modifying exit code
 *
 * @example
 * ```php
 * class ProfilingListener extends TerminateListener
 * {
 *     protected function handleTerminate(ConsoleTerminateEvent $event): void
 *     {
 *         $event->getOutput()->writeln(sprintf(
 *             '<info>Memory: %.2f MB</info>',
 *             memory_get_peak_usage(true) / 1024 / 1024,
 *         ));
 *     }
 * }
 * ```
 */
abstract class TerminateListener extends AbstractEventListener
{
    /**
     * Create terminate listener from callable
     *
     * @param Closure(ConsoleTerminateEvent): void $callback Handler callback
     * @param int $priority Listener priority
     * @return EventListenerInterface Listener instance
     */
    public static function fromCallable(
        Closure $callback,
        string $eventName = ConsoleEvents::TERMINATE,
        int $priority = 0,
    ): EventListenerInterface {
        return new class($callback, $priority) extends TerminateListener {
            public function __construct(
                private readonly Closure $callback,
                int $priority,
            ) {
                parent::__construct($priority);
            }

            protected function handleTerminate(ConsoleTerminateEvent $event): void
            {
                ($this->callback)($event);
            }
        };
    }

    /**
     * Get event name
     *
     * @return string ConsoleEvents::TERMINATE
     */
    public function getEventName(): string
    {
        return ConsoleEvents::TERMINATE;
    }

    /**
     * Check if listener supports the event
     *
     * Validates event type and delegates to supportsTerminate().
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     * @return bool True if event is ConsoleTerminateEvent and supportsTerminate() returns true
     */
    public function supports(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): bool {
        return $event instanceof ConsoleTerminateEvent && $this->supportsTerminate($event);
    }

    /**
     * Check if listener supports the terminate event
     *
     * Override to filter specific conditions (exit code, command, etc.).
     *
     * @param ConsoleTerminateEvent $event Terminate event
     * @return bool True if termination should be handled
     */
    protected function supportsTerminate(ConsoleTerminateEvent $event): bool
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
        $this->handleTerminate($event);
    }

    /**
     * Handle the terminate event
     *
     * Implement termination-specific handling logic.
     *
     * @param ConsoleTerminateEvent $event Terminate event
     */
    abstract protected function handleTerminate(ConsoleTerminateEvent $event): void;
}
