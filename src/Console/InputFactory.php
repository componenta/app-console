<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Default input factory using ArgvInput
 *
 * Creates input instances from command-line arguments.
 * If no arguments provided, uses global $argv.
 */
final readonly class InputFactory implements InputFactoryInterface
{
    /**
     * Create input factory
     *
     * @param array<string>|null $argv Command-line arguments (null for global $argv)
     */
    public function __construct(
        private ?array $argv = null,
    ) {}

    /**
     * Create input instance from argv
     *
     * @return InputInterface ArgvInput instance
     */
    public function createInput(): InputInterface
    {
        return new ArgvInput($this->argv);
    }
}
