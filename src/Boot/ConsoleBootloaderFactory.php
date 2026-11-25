<?php

declare(strict_types=1);

namespace Componenta\App\Boot;

use Componenta\App\Build\PhpMapFile;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Console\ConfigKey;
use Componenta\App\Console\ConsoleCommandRegistryInterface;
use Componenta\Config\ContainerValue;
use Componenta\Stdlib\PathResolverInterface;

final class ConsoleBootloaderFactory
{
    public function __invoke(ContainerValue $container): ConsoleBootloader
    {
        $map = null;
        if ($container->has(AppConfigKey::DISCOVERY_GENERATION)) {
            $map = PhpMapFile::read(
                $container->get(PathResolverInterface::class, PathResolverInterface::class)->resolve(ConfigKey::COMMAND_MAP_FILE),
                $container->get(AppConfigKey::DISCOVERY_GENERATION),
            );
        }
        return new ConsoleBootloader($container->get(ConsoleCommandRegistryInterface::class, ConsoleCommandRegistryInterface::class), $map);
    }
}
