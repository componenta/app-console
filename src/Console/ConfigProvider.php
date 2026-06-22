<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Componenta\App\Boot\ConsoleBootloader;
use Componenta\App\Boot\ConsoleBootTargetAdapter;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Console\Command\CacheClearCommand;
use Componenta\App\Console\Command\PreloadCommand;
use Componenta\Config\ConfigProvider as BaseConfigProvider;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getConfig(): array
    {
        return [
            AppConfigKey::APP_ADAPTERS => [
                ConsoleAppAdapter::class,
            ],
            AppConfigKey::BOOT_TARGET_ADAPTERS => [
                ConsoleBootTargetAdapter::class,
            ],
            AppConfigKey::BOOTLOADERS => [
                ConsoleBootloader::class,
            ],
            ConfigKey::COMMANDS => [
                BuildCommand::class,
                CacheClearCommand::class,
                PreloadCommand::class,
            ],
        ];
    }

    protected function getFactories(): array
    {
        return [
            EventDispatcherFactoryInterface::class => static fn () => new EventDispatcherFactory(),
        ];
    }

    protected function getAutowires(): array
    {
        return [
            App::class,
            BuildCommand::class,
            CacheClearCommand::class,
            ConsoleAppAdapter::class,
            ConsoleBootloader::class,
            ConsoleBootTargetAdapter::class,
            ConsoleCommandRegistry::class,
            PreloadCommand::class,
        ];
    }

    protected function getAliases(): array
    {
        return [
            ConsoleCommandRegistryInterface::class => ConsoleCommandRegistry::class,
        ];
    }
}
