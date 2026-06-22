<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Signal handling subscriber for graceful shutdown
 *
 * Intercepts POSIX signals (SIGINT, SIGTERM) and enables graceful shutdown.
 * On first signal, sets a flag and prevents immediate exit.
 * On second signal, allows normal termination.
 *
 * Requires the pcntl PHP extension.
 *
 * @example
 * ```php
 * $signal = new SignalSubscriber();
 * $app->addSubscriber($signal);
 *
 * // In your command:
 * while (!$signal->shouldStop()) {
 *     // Process work...
 * }
 * ```
 *
 * @example
 * ```php
 * // Custom signals
 * $signal = new SignalSubscriber([SIGINT, SIGTERM, SIGUSR1]);
 * ```
 */
final class SignalSubscriber implements EventSubscriberInterface
{
    private bool $shouldStop = false;

    /** @var array<int> */
    private readonly array $signals;

    /**
     * Create signal subscriber
     *
     * @param array<int> $signals Signals to handle (default: SIGINT, SIGTERM)
     */
    public function __construct(
        array $signals = [SIGINT, SIGTERM],
    ) {
        $this->signals = $signals;
    }

    /**
     * Get subscribed events
     *
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::SIGNAL => ['onSignal', 100],
        ];
    }

    /**
     * Handle signal event
     *
     * First signal: sets stop flag, shows message, prevents exit.
     * Second signal: allows normal termination with force message.
     *
     * @param ConsoleSignalEvent $event Signal event
     */
    public function onSignal(ConsoleSignalEvent $event): void
    {
        $signal = $event->getHandlingSignal();

        if (!in_array($signal, $this->signals, true)) {
            return;
        }

        if ($this->shouldStop) {
            $event->getOutput()->writeln('<error>Force quit...</error>');
            return;
        }

        $this->shouldStop = true;
        $event->getOutput()->writeln('<comment>Graceful shutdown (press again to force)...</comment>');
        $event->abortExit();
    }

    /**
     * Check if shutdown was requested
     *
     * Use in long-running commands to check for termination requests.
     *
     * @return bool True if stop signal was received
     */
    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }
}
