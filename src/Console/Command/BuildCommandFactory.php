<?php

declare(strict_types=1);

namespace Componenta\App\Console\Command;

use Componenta\App\Build\ApplicationBuildRunner;
use Componenta\App\Build\PreparedBuild;
use Componenta\Config\ContainerValue;
use Componenta\Stdlib\PathResolverInterface;

final class BuildCommandFactory
{
    public function __invoke(ContainerValue $container): BuildCommand
    {
        $paths = $container->get(PathResolverInterface::class, PathResolverInterface::class);
        $environment = $container->config->environment;

        return new BuildCommand(
            static fn (): PreparedBuild => ApplicationBuildRunner::build(
                $paths,
                static fn () => require $paths->resolve('config/config.php'),
                $environment,
            ),
        );
    }
}
