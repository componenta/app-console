<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

/**
 * Interface for console event listeners
 *
 * Defines a structured approach to handling console events with
 * support for event filtering via supports() and priority ordering.
 *
 * Console events:
 * - ConsoleEvents::COMMAND   - Before command execution
 * - ConsoleEvents::TERMINATE - After command execution (success or failure)
 * - ConsoleEvents::ERROR     - When an exception is thrown
 * - ConsoleEvents::SIGNAL    - When a signal (SIGINT, SIGTERM, etc.) is received
 *
 * @see \Symfony\Component\Console\ConsoleEvents
 */
interface EventListenerInterface
{
    /**
     * Get the event name this listener handles
     *
     * Should return one of ConsoleEvents::* constants.
     *
     * @return string Event name (e.g., ConsoleEvents::COMMAND)
     */
    public function getEventName(): string;

    /**
     * Get listener priority
     *
     * Higher values execute earlier. Typical ranges:
     * - 1000+: Very early (setup, profiling start)
     * - 100: Early (logging, validation)
     * - 0: Normal
     * - -100: Late (cleanup, logging)
     * - -1000: Very late (profiling end)
     *
     * @return int Priority value
     */
    public function getPriority(): int;

    /**
     * Check if this listener supports the given event
     *
     * Allows filtering events before handle() is called.
     * Return false to skip handling for specific events.
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     * @return bool True if this listener should handle the event
     */
    public function supports(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): bool;

    /**
     * Handle the console event
     *
     * Called by the event dispatcher when the subscribed event occurs
     * and supports() returns true.
     *
     * @param ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event
     */
    public function __invoke(
        ConsoleCommandEvent|ConsoleErrorEvent|ConsoleTerminateEvent|ConsoleSignalEvent $event,
    ): void;
}
