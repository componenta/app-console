<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;


/**
 * Locking subscriber to prevent parallel command execution
 *
 * Acquires a lock before command execution and releases it after.
 * If lock cannot be acquired (command already running), disables execution.
 *
 * Requires symfony/lock package.
 *
 * @example
 * ```php
 * // Lock all commands
 * $app->addSubscriber(new LockingSubscriber($lockFactory));
 *
 * // Lock specific commands only
 * $app->addSubscriber(new LockingSubscriber(
 *     lockFactory: $lockFactory,
 *     commands: ['app:import', 'app:sync'],
 * ));
 *
 * // Custom TTL (for long-running commands)
 * $app->addSubscriber(new LockingSubscriber(
 *     lockFactory: $lockFactory,
 *     ttl: 7200, // 2 hours
 * ));
 * ```
 */
final class LockingSubscriber implements EventSubscriberInterface
{
    private ?LockInterface $lock = null;

    /** @var list<string> */
    private readonly array $commands;

    /**
     * Create locking subscriber
     *
     * @param LockFactory $lockFactory Symfony Lock factory
     * @param list<string> $commands Command names to lock (empty = all commands)
     * @param int $ttl Lock time-to-live in seconds
     */
    public function __construct(
        private readonly LockFactory $lockFactory,
        array $commands = [],
        private readonly int $ttl = 3600,
    ) {
        $this->commands = $commands;
    }

    /**
     * Get subscribed events
     *
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand', 500],
            ConsoleEvents::TERMINATE => ['onTerminate', -500],
        ];
    }

    /**
     * Attempt to acquire lock before command execution
     *
     * If lock cannot be acquired, disables command and sets failure exit code.
     *
     * @param ConsoleCommandEvent $event Command event
     */
    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->shouldLock($command)) {
            return;
        }

        $lockName = 'console_' . $command->getName();
        $this->lock = $this->lockFactory->createLock($lockName, $this->ttl);

        if (!$this->lock->acquire()) {
            $event->getOutput()->writeln(sprintf(
                '<error>Command "%s" is already running</error>',
                $command->getName(),
            ));
            $event->disableCommand();
            $event->setExitCode(Command::FAILURE);
        }
    }

    /**
     * Release lock after command completion
     *
     * @param ConsoleTerminateEvent $event Terminate event
     */
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $this->lock?->release();
        $this->lock = null;
    }

    /**
     * Check if command should be locked
     *
     * @param Command|null $command Command instance
     * @return bool True if locking should be applied
     */
    private function shouldLock(?Command $command): bool
    {
        if ($command === null) {
            return false;
        }

        if ($this->commands === []) {
            return true;
        }

        return in_array($command->getName(), $this->commands, true);
    }
}
