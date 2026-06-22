<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Composite factory for console input and output
 *
 * Combines InputFactoryInterface and OutputFactoryInterface into a single
 * factory class for convenient creation of both input and output instances.
 *
 * @example
 * ```php
 * // Using defaults
 * $io = new IOFactory();
 *
 * // Custom factories
 * $io = new IOFactory(
 *     inputFactory: new InputFactory(['bin/console', 'command']),
 *     outputFactory: new OutputFactory(OutputInterface::VERBOSITY_VERBOSE),
 * );
 *
 * $input = $io->createInput();
 * $output = $io->createOutput();
 * ```
 */
final readonly class IOFactory
{
    /**
     * Create IO factory
     *
     * @param InputFactoryInterface $inputFactory Factory for creating input instances
     * @param OutputFactoryInterface $outputFactory Factory for creating output instances
     */
    public function __construct(
        private InputFactoryInterface $inputFactory = new InputFactory(),
        private OutputFactoryInterface $outputFactory = new OutputFactory(),
    ) {}

    /**
     * Create input instance
     *
     * Delegates to the configured input factory.
     *
     * @return InputInterface Console input instance
     */
    public function createInput(): InputInterface
    {
        return $this->inputFactory->createInput();
    }

    /**
     * Create output instance
     *
     * Delegates to the configured output factory.
     *
     * @return OutputInterface Console output instance
     */
    public function createOutput(): OutputInterface
    {
        return $this->outputFactory->createOutput();
    }
}
