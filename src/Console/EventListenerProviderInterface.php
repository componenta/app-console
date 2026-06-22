<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Interface for registering event listeners and subscribers
 *
 * Provides a unified API for adding event handlers to console applications.
 * Supports both single-event listeners (EventListenerInterface) and
 * multi-event subscribers (EventSubscriberInterface).
 */
interface EventListenerProviderInterface
{
    /**
     * Add an event listener
     *
     * Listeners handle a single event type and are configured via
     * EventListenerInterface methods (getEventName, getPriority).
     *
     * @param EventListenerInterface $listener Listener to add
     */
    public function addListener(EventListenerInterface $listener): void;

    /**
     * Add an event subscriber
     *
     * Subscribers can handle multiple events and define their own
     * event-to-method mapping via getSubscribedEvents().
     *
     * @param EventSubscriberInterface $subscriber Subscriber to add
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void;
}
