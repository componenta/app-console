# Componenta App Console

Symfony Console integration for Componenta applications: a CLI application, boot target, command registry and maintenance commands.

## Installation

~~~bash
composer require componenta/app-console
~~~

The package requires PHP 8.5.13 or later and exposes `Componenta\App\Console\ConfigProvider` through Composer metadata. `componenta/composer-plugin` adds it to the generated provider list.

## Registered services

The provider registers the CLI application service in `AppConfigKey::APPLICATIONS`, `ConsoleBootTargetAdapter` in `AppConfigKey::BOOT_TARGET_ADAPTERS`, and `ConsoleBootloader` in `AppConfigKey::BOOTLOADERS`. It registers the command registry, event-dispatcher factory and the BuildCommand and CleanCommand factories.

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
| `app:build` | Compose current source configuration, run registered builders and publish one generation. |
| `app:clean` | Unpublish the current generation without loading providers or deleting files pinned by running workers. |

Dispatch these commands **before** ordinary application bootstrap. The standard Skeleton entry point does this:

~~~php
use Componenta\App\Console\Build\BuildConsole;
use Componenta\App\Scope;
use Componenta\Stdlib\PathResolver;
use function Componenta\App\run;

$paths = new PathResolver(dirname(__DIR__));
$buildCode = BuildConsole::run($paths);
if ($buildCode !== null) {
    exit($buildCode);
}
run(Scope::CLI, $paths);
~~~

Use the full command names `app:build` and `app:clean` for deployment. `BuildConsole` handles their options, help and version without booting the ordinary application. It returns `null` for other commands. BuildCommand/CleanCommand remain registered for normal command listings and custom console hosts. Their factories use the default `config/config.php`; applications with a different configuration entry point can supply their own command closures or call `ApplicationBuildRunner` directly. Calling a build command after ordinary bootstrap cannot undo bootloader side effects that have already occurred.

`ApplicationBuildRunner` selects source before configuration providers are invoked and creates a fresh container. It never runs runtime bootloaders. Newly added `#[AsConfig]` providers and removed source declarations are reflected in the **first** rebuild, without a preceding clean. Providers still execute at build time to define the build; the complete Config object and live services are not serialized. Structural inputs used by domain maps must stay consistent with the release/profile identity.

`ConsoleCommandBuilder::build(BuildContext $build)` writes the versioned `console.commands` artifact. It records attribute membership only. At runtime, the bootloader reads the `PreparedBuild` selected at bootstrap through `AppConfigKey::PREPARED_BUILD`; command construction, attribute instantiation, aliases and configured-command precedence remain unchanged. Missing or invalid optional maps use the original reflection path. An empty map is a valid prepared result.

Builders receive their source dependencies through constructors and write through the context passed to `build()`. Publication belongs to App. A failing builder produces a nonzero exit status and leaves the previous generation selected. Unsupported optional optimizations are reported with their source-fallback reason. An empty builder list publishes a valid empty generation.

`BuildCommand` now accepts `Closure(): PreparedBuild`; `CleanCommand` accepts `Closure(): void`. The old per-builder cleaner interface, output filename arguments and `ConfigKey::COMMAND_MAP_FILE` have been removed. Clean only removes `current.json`; it is repeatable and works even if application configuration is broken. Remove retained generations only during offline release cleanup after old workers have stopped.

`APP_ENV` does not select or require prepared mode. Use the same `APP_BUILD_ID` for build and runtime to bind artifacts to an immutable release/profile. Rebuild after structural source/configuration changes, then restart workers. See the App README for explicit required-artifact deployment policies and artifact schemas.

## Public API

- `ConsoleCommandRegistryInterface` registers commands and checks class/name uniqueness.
- `ConsoleBootTargetInterface` lets bootloaders add commands to the application.
- `InputFactoryInterface`, `OutputFactoryInterface` and `IOFactory` provide console input and output.
- `ConfigKey::COMMANDS` registers command service IDs.
