<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Componenta\App\Boot\ConsoleBootloader;
use Componenta\App\Boot\ConsoleBootloaderFactory;
use Componenta\App\Config\DiscoveryAwareConfigProviderInterface;
use Componenta\App\Console\Build\ConsoleCommandBuilder;
use Componenta\App\Console\Build\ConsoleCommandBuilderFactory;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\App\Boot\ConsoleBootTargetAdapter;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Console\Command\BuildCommandFactory;
use Componenta\App\Console\Command\CleanCommand;
use Componenta\App\Console\Command\CleanCommandFactory;
use Componenta\Config\ConfigProvider as BaseConfigProvider;

final class ConfigProvider extends BaseConfigProvider implements DiscoveryAwareConfigProviderInterface
{
    public private(set) ?ClassIteratorInterface $discovered = null;

    public function withDiscovered(?ClassIteratorInterface $discovered): static
    {
        $provider = clone $this;
        $provider->discovered = $discovered;
        return $provider;
    }

    protected function getConfig(): array
    {
        return [
            AppConfigKey::BUILDERS => $this->discovered !== null ? [ConsoleCommandBuilder::class] : [],
            AppConfigKey::APP_ADAPTERS => [ConsoleAppAdapter::class],
            AppConfigKey::BOOT_TARGET_ADAPTERS => [ConsoleBootTargetAdapter::class],
            AppConfigKey::BOOTLOADERS => [ConsoleBootloader::class],
            ConfigKey::COMMANDS => [BuildCommand::class, CleanCommand::class],
        ];
    }

    protected function getFactories(): array
    {
        return [
            BuildCommand::class => BuildCommandFactory::class,
            CleanCommand::class => CleanCommandFactory::class,
            ConsoleCommandBuilder::class => ConsoleCommandBuilderFactory::class,
            ConsoleBootloader::class => ConsoleBootloaderFactory::class,
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
