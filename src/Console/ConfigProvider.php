<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Componenta\App\Boot\ConsoleBootloader;
use Componenta\App\Boot\ConsoleBootTargetAdapter;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Console\Command\BuildCommandFactory;
use Componenta\App\Console\Command\CacheClearCommand;
use Componenta\Config\ConfigProvider as BaseConfigProvider;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getConfig(): array
    {
        return [
            AppConfigKey::APP_ADAPTERS => [ConsoleAppAdapter::class],
            AppConfigKey::BOOT_TARGET_ADAPTERS => [ConsoleBootTargetAdapter::class],
            AppConfigKey::BOOTLOADERS => [ConsoleBootloader::class],
            ConfigKey::COMMANDS => [BuildCommand::class, CacheClearCommand::class],
        ];
    }

    protected function getFactories(): array
    {
        return [
            BuildCommand::class => BuildCommandFactory::class,
            EventDispatcherFactoryInterface::class => static fn () => new EventDispatcherFactory(),
        ];
    }

    protected function getAliases(): array
    {
        return [
            ConsoleCommandRegistryInterface::class => ConsoleCommandRegistry::class,
        ];
    }
}
