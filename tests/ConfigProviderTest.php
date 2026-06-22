<?php

declare(strict_types=1);

use Componenta\App\Boot\ConsoleBootTargetAdapter;
use Componenta\App\Boot\ConsoleBootloader;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Console\Command\CacheClearCommand;
use Componenta\App\Console\Command\PreloadCommand;
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

    it('registers application maintenance commands', function (): void {
        $config = (new ConfigProvider())();

        expect($config[ConsoleConfigKey::COMMANDS])->toBe([
            BuildCommand::class,
            CacheClearCommand::class,
            PreloadCommand::class,
        ])->and($config[DependencyConfigKey::DEPENDENCIES][DependencyConfigKey::AUTOWIRES])->toContain(
            BuildCommand::class,
            CacheClearCommand::class,
            PreloadCommand::class,
        );
    });
});
