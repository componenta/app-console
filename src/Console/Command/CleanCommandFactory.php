<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Componenta\App\Build\ApplicationBuildOrchestrator;
use Componenta\Config\ContainerValue;

final class CleanCommandFactory
{
    public function __invoke(ContainerValue $container): CleanCommand
    {
        return new CleanCommand(
            static fn (): ApplicationBuildOrchestrator => $container->get(
                ApplicationBuildOrchestrator::class,
                ApplicationBuildOrchestrator::class,
            ),
        );
    }
}
