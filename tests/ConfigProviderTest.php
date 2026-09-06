<?php

declare(strict_types=1);

use Componenta\App\Boot\ConsoleBootTargetAdapter;
use Componenta\App\Boot\ConsoleBootloader;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Console\Command\CacheClearCommand;
use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
use Componenta\App\Console\ConfigProvider;
use Componenta\App\Console\ConsoleAppAdapter;
use Componenta\Config\ConfigKey as DependencyConfigKey;

describe('console app config provider', function (): void {
    it('registers console runtime adapters and bootloader', function (): void {
        $config = (new ConfigProvider())();

        expect($config[AppConfigKey::APP_ADAPTERS])->toContain(ConsoleAppAdapter::class)
            ->and($config[AppConfigKey::BOOT_TARGET_ADAPTERS])->toContain(ConsoleBootTargetAdapter::class)
            ->and($config[AppConfigKey::BOOTLOADERS])->toContain(ConsoleBootloader::class);
    });

    it('registers app build in the runtime container command graph', function (): void {
        $config = (new ConfigProvider())();

        expect($config[ConsoleConfigKey::COMMANDS])->toBe([
            BuildCommand::class,
            CacheClearCommand::class,
        ])
            ->and($config[DependencyConfigKey::DEPENDENCIES])->not->toHaveKey(
                DependencyConfigKey::INVOKABLES,
            );
    });
});
