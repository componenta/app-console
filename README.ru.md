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
| `ConfigKey::APP_BY_SCOPE[Scope::CLI->value]` | Связывает область `Scope::CLI` непосредственно с `App::class`. |
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
| `app:build` | Записывает кеши конфигурации и контейнера и компилирует generated DI entry resolver для найденных concrete-классов. Build fingerprint проверяет согласованность resolver/cache в production без хеширования исходников при каждом bootstrap. Команда должна запускаться с `APP_ENV=development`. |
| `app:preload` | Генерирует `preload.php` из существующих сборочных артефактов. |
| `app:cache:clear` | Очищает каталоги сборочного, dev- и runtime-кеша. Опции `--build`, `--dev`, `--runtime` ограничивают область очистки. |

`app:build` заново загружает исходную конфигурацию из стандартного `config/config.php` и создаёт отдельный build-container с отключённым кешированием. Текущая прогретая dev-конфигурация не подмешивается в результат, поэтому удалённые маршруты, обработчики и значения compile delta не попадут в новый production artifact.

Discovery финализируется один раз, после чего все listener compilers и зарегистрированные cache contributors получают один и тот же снимок исходных данных. Пустые массивы, `null` и стандартные значения `false` не записываются. Обязательный маркер версии, например пустая CQRS map v2 `['version' => 2]`, сохраняется, потому что это не пустая секция.

Generated resolver и его release fingerprint записываются в container cache парой. Production проверяет эту пару без повторного SHA-256 исходников приложения при каждом bootstrap. Повторяйте `app:build` после изменений провайдеров, обнаруживаемых PHP-классов, маршрутов, CQRS metadata, resolver chains или deployment dependencies.

## Основной API

- `ConsoleCommandRegistryInterface` хранит команды консольного приложения.
- `ConsoleBootTargetInterface` является целевым объектом загрузки для пакетов, которые добавляют команды.
- `InputFactoryInterface`, `OutputFactoryInterface` и `IOFactory` адаптируют ввод и вывод Symfony Console.
- `ConfigKey::COMMANDS` является production-safe точкой регистрации команд.

## Связанные Пакеты

- [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает области выполнения, выбор приложения и загрузчики.
- [`componenta/error-handler`](https://github.com/componenta/error-handler/blob/main/README.ru.md) дает контракты обработки ошибок, которые используют консольные слушатели.
- [`componenta/cycle-app`](https://github.com/componenta/cycle-app/blob/main/README.ru.md) добавляет команды `db:*`.
- [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) добавляет `router:list`.
