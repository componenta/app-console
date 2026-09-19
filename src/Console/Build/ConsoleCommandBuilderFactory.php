<?php

declare(strict_types=1);

namespace Componenta\App\Console\Build;

use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Console\ConfigKey;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Config\ContainerValue;
use Componenta\Stdlib\PathResolverInterface;

final class ConsoleCommandBuilderFactory
{
    public function __invoke(ContainerValue $container): ConsoleCommandBuilder
    {
        return new ConsoleCommandBuilder(
            $container->get(AppConfigKey::DISCOVERY_SOURCE, ClassIteratorInterface::class),
            $container->get(PathResolverInterface::class, PathResolverInterface::class)->resolve(ConfigKey::COMMAND_MAP_FILE),
        );
    }
}
