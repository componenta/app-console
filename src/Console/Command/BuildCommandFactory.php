<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Componenta\App\Build\ApplicationBuildOrchestrator;
use Componenta\Config\ContainerValue;

final class BuildCommandFactory
{
    public function __invoke(ContainerValue $container): BuildCommand
    {
        return new BuildCommand(
            static fn (): ApplicationBuildOrchestrator => $container->get(
                ApplicationBuildOrchestrator::class,
                ApplicationBuildOrchestrator::class,
            ),
        );
    }
}