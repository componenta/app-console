<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Componenta\App\Boot\ConsoleBootloader;
use Componenta\App\Boot\ConsoleBootTargetAdapter;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Scope;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Console\Command\CacheClearCommand;
use Componenta\App\Console\Command\PreloadCommand;
use Componenta\App\Console\Compile\ConsoleCommandCompiler;
use Componenta\App\Console\Compile\ConsoleCommandMapContributor;
use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Psr\Container\ContainerInterface;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getConfig(): array
    {
        return [
            AppConfigKey::APP_BY_SCOPE => [
                Scope::CLI->value => App::class,
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
            AppConfigKey::COMPILE_CACHE_CONTRIBUTORS => [
                ConsoleCommandMapContributor::class,
            ],
        ];
    }

    protected function getFactories(): array
    {
        return [
            App::class => static fn (ContainerInterface $container): App => App::createFromContainer($container),
            EventDispatcherFactoryInterface::class => static fn () => new EventDispatcherFactory(),
        ];
    }

    protected function getAutowires(): array
    {
        return [
            BuildCommand::class,
            CacheClearCommand::class,
            ConsoleBootloader::class,
            ConsoleBootTargetAdapter::class,
            ConsoleCommandRegistry::class,
            PreloadCommand::class,
        ];
    }

    protected function getInvokables(): array
    {
        return [
            ConsoleCommandCompiler::class,
            ConsoleCommandMapContributor::class,
        ];
    }

    protected function getAliases(): array
    {
        return [
            ConsoleCommandRegistryInterface::class => ConsoleCommandRegistry::class,
        ];
    }
}
