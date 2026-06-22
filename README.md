# Componenta App Console

Console runtime integration for Componenta applications. The package connects `componenta/app` with Symfony Console, registers console boot targets, provides a command registry, and contributes framework maintenance commands.

Use this package when an application has CLI commands. It does not provide the base application kernel; that belongs to `componenta/app`.

## Installation

```bash
composer require componenta/app-console
```

The package exposes `Componenta\App\Console\ConfigProvider` through Composer metadata, so `componenta/composer-plugin` can add it to the generated provider file.

## Dependencies

The package requires PHP `^8.4`, `componenta/app`, `componenta/class-finder`, `componenta/config`, `componenta/di`, `componenta/error-handler`, `componenta/path-resolver`, `componenta/reflection`, `componenta/var-export`, PSR-11, PSR-3, Symfony Console, Symfony EventDispatcher, and Symfony Lock.

## Registered Services

`ConfigProvider` registers:

| Service or config key | Purpose |
|---|---|
| `ConsoleAppAdapter` | Creates a console application for `Scope::CLI`. |
| `ConsoleBootTargetAdapter` | Adapts the console application to a boot target. |
| `ConsoleBootloader` | Boots console commands into the target registry. |
| `ConsoleCommandRegistryInterface` | Alias to `ConsoleCommandRegistry`. |
| `EventDispatcherFactoryInterface` | Factory for Symfony Console event dispatchers. |
| `ConfigKey::COMMANDS` | List of command service ids registered by packages or the application. |

## Runtime Behavior

`ConsoleBootloader` runs in console scope. It reads all configured command service ids from `Componenta\App\Console\ConfigKey::COMMANDS`, resolves them from the container, and registers them in the Symfony Console application.

In development mode it also scans the class iterator for Symfony `#[AsCommand]` attributes. Attribute-discovered commands are skipped when the same class was already registered through config. In production mode command discovery is disabled; commands must be present in the built configuration.

Application-local commands can be registered from `config/console.php` when that file is loaded by the application config graph:

```php
use App\Console\ImportPostsCommand;
use Componenta\App\Console\ConfigKey;

return [
    ConfigKey::COMMANDS => [
        ImportPostsCommand::class,
    ],
];
```

Packages should contribute commands from their own `ConfigProvider` by appending to the same key.

## Maintenance Commands

The package registers these commands:

| Command | Purpose |
|---|---|
| `app:build` | Writes build cache files for configuration and the DI container. Must run with `APP_ENV=development` so it builds from source configuration instead of existing production cache. |
| `app:preload` | Generates `preload.php` from existing build cache artifacts. |
| `app:cache:clear` | Clears build, development, and runtime cache directories. Use `--build`, `--dev`, or `--runtime` to limit the scope. |

## Public API

- `ConsoleCommandRegistryInterface` stores command definitions for the console application.
- `ConsoleBootTargetInterface` is the boot target for packages that want to add commands.
- `InputFactoryInterface`, `OutputFactoryInterface`, and `IOFactory` adapt Symfony Console input and output.
- `ConfigKey::COMMANDS` is the production-safe command registration point.

## Related Packages

- [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) explains scopes, adapters, and bootloaders.
- [`componenta/error-handler`](https://github.com/componenta/error-handler/blob/main/README.md) provides the error handling contracts used by console listeners.
- [`componenta/cycle-app`](https://github.com/componenta/cycle-app/blob/main/README.md) contributes `db:*` commands.
- [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md) contributes `router:list`.
