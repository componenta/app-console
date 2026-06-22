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
 * Abstract listener for ConsoleEvents::ERROR
 *
 * Triggered when an exception is thrown during command execution.
 * Use cases include:
 * - Logging errors
 * - Reporting to error tracking services (Sentry, etc.)
 * - Modifying exception or exit code
 * - Custom error output formatting
 *
 * @example
 * ```php
 * class SentryErrorListener extends ErrorListener
 * {
 *     protected function supportsError(ConsoleErrorEvent $event): bool
 *     {
 *         // Skip reporting for user-facing exceptions
 *         return !$event->getError() instanceof UserException;
 *     }
 *
 *     protected function handleError(ConsoleErrorEvent $event): void
 *     {
 *         $this->sentry->captureException($event->getError());
 *     }
 * }
 * ```
 */
abstract class ErrorListener extends AbstractEventListener
{
    /**
     * Create error listener from callable
     *
     * @param Closure(ConsoleErrorEvent): void $callback Handler callback
     * @param int $priority Listener priority
     * @return EventListenerInterface Listener instance
     */
    public static function fromCallable(
        Closure $callback,
        string $eventName = ConsoleEvents::ERROR,
        int $priority = 0,
    ): EventListenerInterface {
        return new class($callback, $priority) extends ErrorListener {
            public function __construct(
                private readonly Closure $callback,
                int $priority,
            ) {
                parent::__construct($priority);
            }

            protected function handleError(ConsoleErrorEvent $event): void
            {
                ($this->callback)($event);
            }
        };
    }

    /**
     * Get event name
     *
     * @return string ConsoleEvents::ERROR
     */
    public function getEventName(): string
    {
        return ConsoleEvents::ERROR;
    }

    /**
     * Check if listener supports the event
     *
     * Validates event type and delegates to supportsError().
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     * @return bool True if event is ConsoleErrorEvent and supportsError() returns true
     */
    public function supports(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): bool {
        return $event instanceof ConsoleErrorEvent && $this->supportsError($event);
    }

    /**
     * Check if listener supports the error event
     *
     * Override to filter specific exception types or conditions.
     *
     * @param ConsoleErrorEvent $event Error event
     * @return bool True if error should be handled
     */
    protected function supportsError(ConsoleErrorEvent $event): bool
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
        $this->handleError($event);
    }

    /**
     * Handle the error event
     *
     * Implement error-specific handling logic.
     *
     * @param ConsoleErrorEvent $event Error event
     */
    abstract protected function handleError(ConsoleErrorEvent $event): void;
}
