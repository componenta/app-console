# Componenta App Console

Symfony Console integration for Componenta applications: a CLI application adapter, boot target, command registry and maintenance commands.

## Installation

~~~bash
composer require componenta/app-console
~~~

The package requires PHP 8.4 or later and exposes `Componenta\App\Console\ConfigProvider` through Composer metadata. `componenta/composer-plugin` adds it to the generated provider list.

## Registered services

The provider adds `ConsoleAppAdapter` to `AppConfigKey::APP_ADAPTERS`, `ConsoleBootTargetAdapter` to `AppConfigKey::BOOT_TARGET_ADAPTERS`, and `ConsoleBootloader` to `AppConfigKey::BOOTLOADERS`. It registers the command registry, event-dispatcher factory and the BuildCommand and CleanCommand factories.

`ConsoleBootloader` resolves command IDs from `Componenta\App\Console\ConfigKey::COMMANDS` through the existing container. When a `ClassIteratorInterface` is available, it also discovers Symfony `#[AsCommand]` attributes. Configured and discovered commands follow the same registration path in development and production. A class is registered once; different command classes sharing a name cause an error.

## Registering commands

Packages and applications add command service IDs from a ConfigProvider:

~~~php
use App\Console\ImportPostsCommand;
use Componenta\App\Console\ConfigKey;
use Componenta\Config\ConfigProvider as BaseConfigProvider;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getConfig(): array
    {
        return [
            ConfigKey::COMMANDS => [ImportPostsCommand::class],
        ];
    }
}
~~~

The same array can be returned by a file provider included in the application's configuration.

## Maintenance commands

| Command | Purpose |
|---|---|
| `app:build` | Runs services registered in `Componenta\App\ConfigKey::BUILDERS` in order. |
| `app:clean` | Calls `clean(): void` on registered builders implementing `ApplicationBuildCleanerInterface`. Each builder removes its own artifacts. |

Run the build through the ordinary CLI entry point:

~~~bash
php bin/console.php app:build
~~~

BuildCommand and CleanCommand each receive a `Closure(): ApplicationBuildOrchestrator`. Each factory closes over the existing container; the closure is called only in `execute()`. `list` and `--help` therefore do not instantiate builders or request discovery for the build. Ordinary application preparation still takes place.

With discovery configured, `ConsoleCommandBuilder` writes `var/cache/build/commands.php`, recording whether each class carries `AsCommand`. The runtime uses it with prepared discovery to skip attribute searches for other classes. Command creation and attribute instantiation retain their native runtime behavior. Missing or malformed maps fall back to discovery; source discovery ignores older command maps. Configured commands retain precedence over discovered commands.

Both commands are available in development and production, including before build artifacts exist. `ApplicationBuildOrchestratorFactory` validates all registrations and resolves all builders before execution. An absent or empty builder list succeeds.

Every builder implements `build(): void` and receives its dependencies through its constructor. The builder owns the artifact format, paths, directories and atomic writes. Exceptions stop the sequence and produce a nonzero console exit status; already completed effects remain. Shared original data is provided through DI.

Cleanup uses the same validated builder list, skips builders without the optional interface, and succeeds when artifacts are already absent. It preserves unrelated files and does not recursively clear cache directories. `app:clean` replaces `app:cache:clear`; directory selection options are removed. Both commands use normal bootstrap with available maps. The next process after cleanup starts without those maps.

See the [App builder API](https://github.com/componenta/app/blob/main/README.md#application-builders) for registration examples.

## Public API

- `ConsoleCommandRegistryInterface` registers commands and checks class/name uniqueness.
- `ConsoleBootTargetInterface` lets bootloaders add commands to the application.
- `InputFactoryInterface`, `OutputFactoryInterface` and `IOFactory` provide console input and output.
- `ConfigKey::COMMANDS` registers command service IDs.
