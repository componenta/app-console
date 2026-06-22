# Componenta App Console

Интеграция консольной области выполнения для Componenta-приложений. Пакет связывает `componenta/app` с Symfony Console, регистрирует консольные целевые объекты загрузки, предоставляет реестр команд и добавляет команды обслуживания приложения.

Используйте этот пакет, когда приложению нужны CLI-команды. Базовое ядро приложения находится в `componenta/app`.

## Установка

```bash
composer require componenta/app-console
```

Пакет публикует `Componenta\App\Console\ConfigProvider` через метаданные Composer, поэтому `componenta/composer-plugin` может добавить его в сгенерированный файл провайдеров.

## Зависимости

Пакет требует PHP `^8.4`, `componenta/app`, `componenta/class-finder`, `componenta/config`, `componenta/di`, `componenta/error-handler`, `componenta/path-resolver`, `componenta/reflection`, `componenta/var-export`, PSR-11, PSR-3, Symfony Console, Symfony EventDispatcher и Symfony Lock.

## Что Регистрирует Пакет

`ConfigProvider` регистрирует:

| Сервис или ключ конфигурации | Назначение |
|---|---|
| `ConsoleAppAdapter` | Создает консольное приложение для `Scope::CLI`. |
| `ConsoleBootTargetAdapter` | Адаптирует консольное приложение к целевому объекту загрузки. |
| `ConsoleBootloader` | Загружает команды в реестр Symfony Console. |
| `ConsoleCommandRegistryInterface` | Псевдоним на `ConsoleCommandRegistry`. |
| `EventDispatcherFactoryInterface` | Фабрика диспетчеров событий Symfony Console. |
| `ConfigKey::COMMANDS` | Список entry id команд, которые регистрируют пакеты или приложение. |

## Поведение

`ConsoleBootloader` работает только в консольной области. Он читает все entry id команд из `Componenta\App\Console\ConfigKey::COMMANDS`, получает команды из контейнера и регистрирует их в Symfony Console.

В режиме разработки загрузчик дополнительно проходит по итератору найденных классов и ищет Symfony-атрибут `#[AsCommand]`. Если тот же класс уже зарегистрирован через конфигурацию, повторной регистрации не будет. В боевом режиме обнаружение атрибутов отключено: команды должны быть в собранной конфигурации.

Команды приложения можно добавлять в `config/console.php`, если этот файл подключен к общему графу конфигурации:

```php
use App\Console\ImportPostsCommand;
use Componenta\App\Console\ConfigKey;

return [
    ConfigKey::COMMANDS => [
        ImportPostsCommand::class,
    ],
];
```

Пакеты должны добавлять свои команды через собственный `ConfigProvider` в тот же ключ.

## Команды Обслуживания

Пакет регистрирует:

| Команда | Назначение |
|---|---|
| `app:build` | Записывает сборочные файлы кеша конфигурации и DI-контейнера. Должна запускаться с `APP_ENV=development`, чтобы сборка шла из исходной конфигурации, а не из существующего production-cache. |
| `app:preload` | Генерирует `preload.php` из существующих сборочных артефактов. |
| `app:cache:clear` | Очищает каталоги сборочного, dev- и runtime-кеша. Опции `--build`, `--dev`, `--runtime` ограничивают область очистки. |

## Основной API

- `ConsoleCommandRegistryInterface` хранит команды консольного приложения.
- `ConsoleBootTargetInterface` является целевым объектом загрузки для пакетов, которые добавляют команды.
- `InputFactoryInterface`, `OutputFactoryInterface` и `IOFactory` адаптируют ввод и вывод Symfony Console.
- `ConfigKey::COMMANDS` является production-safe точкой регистрации команд.

## Связанные Пакеты

- [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает области выполнения, адаптеры и загрузчики.
- [`componenta/error-handler`](https://github.com/componenta/error-handler/blob/main/README.ru.md) дает контракты обработки ошибок, которые используют консольные слушатели.
- [`componenta/cycle-app`](https://github.com/componenta/cycle-app/blob/main/README.ru.md) добавляет команды `db:*`.
- [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) добавляет `router:list`.
