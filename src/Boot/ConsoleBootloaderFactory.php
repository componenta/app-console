<?php

declare(strict_types=1);

namespace Componenta\App\Boot;

use Componenta\App\Build\PreparedBuild;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Console\Build\ConsoleCommandBuilder;
use Componenta\App\Console\ConsoleCommandRegistryInterface;
use Componenta\Config\ContainerValue;

final class ConsoleBootloaderFactory
{
    public function __invoke(ContainerValue $container): ConsoleBootloader
    {
        $map = null;
        if ($container->has(AppConfigKey::PREPARED_BUILD)) {
            $map = $container->get(AppConfigKey::PREPARED_BUILD, PreparedBuild::class)->read(ConsoleCommandBuilder::ARTIFACT);
        }
        return new ConsoleBootloader($container->get(ConsoleCommandRegistryInterface::class, ConsoleCommandRegistryInterface::class), $map);
    }
}
