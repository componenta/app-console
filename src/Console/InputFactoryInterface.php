<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Factory interface for creating console input instances
 *
 * Implementations provide input abstraction for console applications,
 * allowing different input sources (argv, array, string) to be used
 * interchangeably.
 */
interface InputFactoryInterface
{
    /**
     * Create a new input instance
     *
     * @return InputInterface Console input instance
     */
    public function createInput(): InputInterface;
}
