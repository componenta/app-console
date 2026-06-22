<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Componenta\App\Boot\Target\ConsoleBootTargetInterface;
use LogicException;
use Symfony\Component\Console\Command\Command;

final class ConsoleCommandRegistry implements ConsoleCommandRegistryInterface
{
    /**
     * @var array<string, Command>
     */
    private array $commandsByName = [];

    /**
     * @var array<class-string, string>
     */
    private array $namesByClass = [];

    public array $commands {
        get => array_values($this->commandsByName);
    }

    public function register(ConsoleBootTargetInterface $target, Command $command): Command
    {
        $class = $command::class;
        $name = $command->getName();

        if ($name === null || $name === '') {
            throw new LogicException(sprintf('Console command %s must have a non-empty name.', $class));
        }

        if (isset($this->namesByClass[$class])) {
            return $this->commandsByName[$this->namesByClass[$class]];
        }

        if (isset($this->commandsByName[$name])) {
            $existing = $this->commandsByName[$name];

            if ($existing::class === $class) {
                $this->namesByClass[$class] = $name;

                return $existing;
            }

            throw new LogicException(sprintf(
                'Console command name "%s" is already registered by %s, cannot register %s.',
                $name,
                $existing::class,
                $class,
            ));
        }

        $registered = $target->add($command);
        $this->commandsByName[$name] = $registered;
        $this->namesByClass[$registered::class] = $name;

        if ($registered::class !== $class) {
            $this->namesByClass[$class] = $name;
        }

        return $registered;
    }

    public function hasName(string $name): bool
    {
        return isset($this->commandsByName[$name]);
    }

    public function hasClass(string $class): bool
    {
        return isset($this->namesByClass[$class]);
    }

    public function toArray(): array
    {
        return array_map(
            static fn(Command $command): array => [
                'name' => $command->getName(),
                'class' => $command::class,
            ],
            $this->commands,
        );
    }
}
