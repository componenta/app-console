<?php

declare(strict_types=1);

namespace Componenta\App\Boot\Target;

use Componenta\App\Console\App;
use Symfony\Component\Console\Command\Command;

final readonly class ConsoleBootTarget implements ConsoleBootTargetInterface
{
    public function __construct(
        private App $app,
    ) {}

    public function add(Command $command): Command
    {
        return $this->app->add($command);
    }

    public function addCommands(array $commands): void
    {
        $this->app->addCommands($commands);
    }
}
