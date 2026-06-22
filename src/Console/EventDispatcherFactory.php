<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Default event dispatcher factory
 *
 * Creates standard Symfony EventDispatcher instances.
 * For pre-configured dispatchers with subscribers, extend this class
 * or implement EventDispatcherFactoryInterface directly.
 *
 * @example
 * ```php
 * // Simple usage
 * $factory = new EventDispatcherFactory();
 * $dispatcher = $factory->createDispatcher();
 *
 * // Pre-configured factory
 * $factory = new class implements EventDispatcherFactoryInterface {
 *     public function createDispatcher(): EventDispatcherInterface
 *     {
 *         $dispatcher = new EventDispatcher();
 *         $dispatcher->addSubscriber(new LoggingSubscriber($logger));
 *         return $dispatcher;
 *     }
 * };
 * ```
 */
final readonly class EventDispatcherFactory implements EventDispatcherFactoryInterface
{
    /**
     * Create a new event dispatcher instance
     *
     * @return EventDispatcherInterface Fresh EventDispatcher instance
     */
    public function createDispatcher(): EventDispatcherInterface
    {
        return new EventDispatcher();
    }
}
