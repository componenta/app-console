<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Default output factory using ConsoleOutput
 *
 * Creates output instances for terminal display with configurable
 * verbosity level and decoration (ANSI colors).
 */
final readonly class OutputFactory implements OutputFactoryInterface
{
    /**
     * Create output factory
     *
     * @param int $verbosity Default verbosity level (VERBOSITY_* constants)
     * @param bool|null $decorated Whether to decorate output with ANSI codes (null for auto-detect)
     */
    public function __construct(
        private int $verbosity = OutputInterface::VERBOSITY_NORMAL,
        private ?bool $decorated = null,
    ) {}

    /**
     * Create console output instance
     *
     * @return OutputInterface ConsoleOutput instance
     */
    public function createOutput(): OutputInterface
    {
        return new ConsoleOutput($this->verbosity, $this->decorated);
    }
}
