<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Componenta\App\Build\ApplicationBuildRunner;
use Componenta\Config\ContainerValue;
use Componenta\Stdlib\PathResolverInterface;

final class CleanCommandFactory
{
    public function __invoke(ContainerValue $container): CleanCommand
    {
        $paths = $container->get(PathResolverInterface::class, PathResolverInterface::class);
        $environment = $container->config->environment;

        return new CleanCommand(static function () use ($paths, $environment): void {
            ApplicationBuildRunner::clean($paths, $environment);
        });
    }
}
