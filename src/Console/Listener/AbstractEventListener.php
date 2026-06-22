<?php

declare(strict_types=1);

namespace Componenta\App\Console\Listener;

use Closure;
use Componenta\App\Console\EventListenerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

/**
 * Abstract base class for console event listeners
 *
 * Provides common functionality for event listeners including:
 * - Priority management
 * - Support filtering via supports() method
 * - Factory method for creating listeners from closures
 *
 * Subclasses must implement getEventName() and handle() methods.
 *
 * @example
 * ```php
 * // Create listener from closure
 * $listener = AbstractEventListener::fromCallable(
 *     callback: fn($event) => echo "Event received",
 *     eventName: ConsoleEvents::COMMAND,
 *     priority: 100,
 * );
 * ```
 */
abstract class AbstractEventListener implements EventListenerInterface
{
    /**
     * Create listener instance
     *
     * @param int $priority Listener priority (higher = earlier execution)
     */
    public function __construct(
        private readonly int $priority = 0,
    ) {}

    /**
     * Create a listener from a callable
     *
     * Factory method for creating simple listeners without subclassing.
     *
     * @param Closure(ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent): void $callback
     * @param string $eventName Event name (ConsoleEvents::* constant)
     * @param int $priority Listener priority
     * @return EventListenerInterface Listener instance
     */
    public static function fromCallable(
        Closure $callback,
        string $eventName,
        int $priority = 0,
    ): EventListenerInterface {
        return new class($callback, $eventName, $priority) extends AbstractEventListener {
            public function __construct(
                private readonly Closure $callback,
                private readonly string $eventName,
                int $priority,
            ) {
                parent::__construct($priority);
            }

            public function getEventName(): string
            {
                return $this->eventName;
            }

            protected function handle(
                ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
            ): void {
                ($this->callback)($event);
            }
        };
    }

    /**
     * Get listener priority
     *
     * @return int Priority value
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Check if listener supports the event
     *
     * Override in subclasses for custom filtering logic.
     * Default implementation accepts all events.
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     * @return bool True if event should be handled
     */
    public function supports(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): bool {
        return true;
    }

    /**
     * Invoke the listener
     *
     * Checks supports() before delegating to handle().
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     */
    public function __invoke(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): void {
        if ($this->supports($event)) {
            $this->handle($event);
        }
    }

    /**
     * Handle the event
     *
     * Implement in subclasses to define event handling logic.
     * Only called when supports() returns true.
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     */
    abstract protected function handle(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): void;
}
