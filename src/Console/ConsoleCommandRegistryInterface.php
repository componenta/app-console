<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Componenta\App\Boot\Target\ConsoleBootTargetInterface;
use Componenta\Arrayable\Arrayable;
use Symfony\Component\Console\Command\Command;

interface ConsoleCommandRegistryInterface extends Arrayable
{
    /**
     * @var list<Command>
     */
    public array $commands { get; }

    public function register(ConsoleBootTargetInterface $target, Command $command): Command;

    public function hasName(string $name): bool;

    public function hasClass(string $class): bool;
}
