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
 * Abstract listener for ConsoleEvents::SIGNAL
 *
 * Triggered when a POSIX signal is received (requires pcntl extension).
 * Common signals:
 * - SIGINT (2): Interrupt from keyboard (Ctrl+C)
 * - SIGTERM (15): Termination request
 * - SIGQUIT (3): Quit from keyboard
 * - SIGUSR1/SIGUSR2: User-defined signals
 *
 * Use cases include:
 * - Graceful shutdown
 * - Progress saving before termination
 * - Custom signal handling
 *
 * @example
 * ```php
 * class GracefulShutdownListener extends SignalListener
 * {
 *     protected function supportsSignal(ConsoleSignalEvent $event): bool
 *     {
 *         return $event->getHandlingSignal() === SIGINT;
 *     }
 *
 *     protected function handleSignal(ConsoleSignalEvent $event): void
 *     {
 *         $event->getOutput()->writeln('<comment>Shutting down gracefully...</comment>');
 *         $event->abortExit(); // Prevent immediate exit
 *     }
 * }
 * ```
 */
abstract class SignalListener extends AbstractEventListener
{
    /**
     * Create signal listener from callable
     *
     * @param Closure(ConsoleSignalEvent): void $callback Handler callback
     * @param int $priority Listener priority
     * @return EventListenerInterface Listener instance
     */
    public static function fromCallable(
        Closure $callback,
        string $eventName = ConsoleEvents::SIGNAL,
        int $priority = 0,
    ): EventListenerInterface {
        return new class($callback, $priority) extends SignalListener {
            public function __construct(
                private readonly Closure $callback,
                int $priority,
            ) {
                parent::__construct($priority);
            }

            protected function handleSignal(ConsoleSignalEvent $event): void
            {
                ($this->callback)($event);
            }
        };
    }

    /**
     * Get event name
     *
     * @return string ConsoleEvents::SIGNAL
     */
    public function getEventName(): string
    {
        return ConsoleEvents::SIGNAL;
    }

    /**
     * Check if listener supports the event
     *
     * Validates event type and delegates to supportsSignal().
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     * @return bool True if event is ConsoleSignalEvent and supportsSignal() returns true
     */
    public function supports(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): bool {
        return $event instanceof ConsoleSignalEvent && $this->supportsSignal($event);
    }

    /**
     * Check if listener supports the signal event
     *
     * Override to filter specific signals.
     *
     * @param ConsoleSignalEvent $event Signal event
     * @return bool True if signal should be handled
     */
    protected function supportsSignal(ConsoleSignalEvent $event): bool
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
        $this->handleSignal($event);
    }

    /**
     * Handle the signal event
     *
     * Implement signal-specific handling logic.
     *
     * @param ConsoleSignalEvent $event Signal event
     */
    abstract protected function handleSignal(ConsoleSignalEvent $event): void;
}
