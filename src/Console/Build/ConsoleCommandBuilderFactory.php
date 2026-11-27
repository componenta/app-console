<?php

declare(strict_types=1);

namespace Componenta\App\Console\Build;

use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Config\ContainerValue;

final class ConsoleCommandBuilderFactory
{
    public function __invoke(ContainerValue $container): ConsoleCommandBuilder
    {
        return new ConsoleCommandBuilder(
            $container->get(AppConfigKey::DISCOVERY_SOURCE, ClassIteratorInterface::class),
        );
    }
}
