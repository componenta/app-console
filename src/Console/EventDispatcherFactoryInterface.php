<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Factory interface for creating event dispatcher instances
 *
 * Allows customization of event dispatcher creation, enabling
 * pre-configuration of subscribers and listeners before the
 * dispatcher is attached to the console application.
 */
interface EventDispatcherFactoryInterface
{
    /**
     * Create a new event dispatcher instance
     *
     * @return EventDispatcherInterface Event dispatcher instance
     */
    public function createDispatcher(): EventDispatcherInterface;
}
