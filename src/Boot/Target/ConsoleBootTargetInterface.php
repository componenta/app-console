<?php

declare(strict_types=1);

namespace Componenta\App\Boot\Target;

use Symfony\Component\Console\Command\Command;

interface ConsoleBootTargetInterface
{
    public function add(Command $command): Command;

    /**
     * @param list<Command> $commands
     */
    public function addCommands(array $commands): void;
}
