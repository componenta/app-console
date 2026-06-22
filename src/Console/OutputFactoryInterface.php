<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Factory interface for creating console output instances
 *
 * Implementations provide output abstraction for console applications,
 * allowing different output targets (console, buffer, null) to be used
 * interchangeably.
 */
interface OutputFactoryInterface
{
    /**
     * Create a new output instance
     *
     * @return OutputInterface Console output instance
     */
    public function createOutput(): OutputInterface;
}
