<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Profiling subscriber for command performance monitoring
 *
 * Measures and displays execution time and memory usage after command completion.
 * Output is only shown when verbosity level meets the configured threshold.
 *
 * Output format:
 * ```
 * Time: 123.45 ms | Memory: 12.34 MB (peak: 23.45 MB)
 * ```
 *
 * @example
 * ```php
 * // Show profiling info only with -v flag
 * $app->addSubscriber(new ProfilingSubscriber(OutputInterface::VERBOSITY_VERBOSE));
 *
 * // Always show profiling info
 * $app->addSubscriber(new ProfilingSubscriber(OutputInterface::VERBOSITY_NORMAL));
 * ```
 */
final class ProfilingSubscriber implements EventSubscriberInterface
{
    private float $startTime;
    private int $startMemory;

    /**
     * Create profiling subscriber
     *
     * @param int $verbosity Minimum verbosity level for output (default: VERBOSE, -v flag)
     */
    public function __construct(
        private readonly int $verbosity = OutputInterface::VERBOSITY_VERBOSE,
    ) {}

    /**
     * Get subscribed events
     *
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand', 1000],
            ConsoleEvents::TERMINATE => ['onTerminate', -1000],
        ];
    }

    /**
     * Record start time and memory
     *
     * @param ConsoleCommandEvent $event Command event
     */
    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->startTime = hrtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Output profiling information
     *
     * Only outputs if verbosity level is sufficient.
     *
     * @param ConsoleTerminateEvent $event Terminate event
     */
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $output = $event->getOutput();

        if ($output->getVerbosity() < $this->verbosity) {
            return;
        }

        $duration = (hrtime(true) - $this->startTime) / 1_000_000;
        $memoryUsed = memory_get_usage(true) - $this->startMemory;
        $memoryPeak = memory_get_peak_usage(true);

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Time: %.2f ms | Memory: %.2f MB (peak: %.2f MB)</info>',
            $duration,
            $memoryUsed / 1024 / 1024,
            $memoryPeak / 1024 / 1024,
        ));
    }
}
