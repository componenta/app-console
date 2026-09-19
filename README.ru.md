# Componenta App Console

Интеграция Symfony Console с Componenta: адаптер CLI-приложения, целевой объект загрузки, реестр команд и команды обслуживания.

## Установка

~~~bash
composer require componenta/app-console
~~~

Требуется PHP 8.4 или новее. Пакет объявляет `Componenta\App\Console\ConfigProvider` в метаданных Composer; `componenta/composer-plugin` добавляет его в сгенерированный список провайдеров.

## Регистрация сервисов

Провайдер добавляет `ConsoleAppAdapter` в `AppConfigKey::APP_ADAPTERS`, `ConsoleBootTargetAdapter` в `AppConfigKey::BOOT_TARGET_ADAPTERS` и `ConsoleBootloader` в `AppConfigKey::BOOTLOADERS`. Он регистрирует реестр команд, фабрику диспетчера событий и фабрики BuildCommand и CleanCommand.

`ConsoleBootloader` получает команды по ID из `Componenta\App\Console\ConfigKey::COMMANDS` через существующий контейнер. Если доступен `ClassIteratorInterface`, загрузчик также ищет Symfony-атрибуты `#[AsCommand]`. Явные и найденные команды проходят одинаковую регистрацию в development и production. Каждый класс регистрируется один раз; одинаковое имя у разных классов команд вызывает ошибку.

## Регистрация команд

Пакеты и приложение добавляют ID сервисов команд через ConfigProvider:

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

Такой же массив может возвращать файловый провайдер, подключённый к конфигурации приложения.

## Команды обслуживания

| Команда | Назначение |
|---|---|
| `app:build` | По порядку вызывает сервисы из `Componenta\App\ConfigKey::BUILDERS`. |
| `app:clean` | Вызывает `clean(): void` у зарегистрированных билдеров с `ApplicationBuildCleanerInterface`. Каждый билдер удаляет свои артефакты. |

Сборка запускается через обычную CLI-точку входа:

~~~bash
php bin/console.php app:build
~~~

BuildCommand и CleanCommand получают `Closure(): ApplicationBuildOrchestrator`. Фабрика создаёт замыкание над существующим контейнером; команда вызывает его только в `execute()`. Поэтому `list` и `--help` не создают билдеры и не запрашивают discovery ради сборки. Обычная подготовка приложения сохраняется.

При настроенном discovery `ConsoleCommandBuilder` записывает `var/cache/build/commands.php` с признаком наличия `AsCommand` для каждого класса. При использовании подготовленного discovery runtime пропускает поиск атрибута у остальных классов. Команды и атрибуты создаются нативно при запуске. Отсутствующая или некорректная карта включает исходный путь; при исходном discovery старые карты команд игнорируются. Явно зарегистрированные команды сохраняют приоритет над обнаруженными.

Обе команды доступны в development и production, в том числе до появления артефактов. `ApplicationBuildOrchestratorFactory` проверяет все регистрации и создаёт все билдеры до начала выполнения. Отсутствующий или пустой список завершается успешно.

Каждый билдер реализует `build(): void` и получает зависимости через конструктор. Билдер отвечает за формат артефактов, пути, каталоги и атомарные записи. Исключение останавливает последовательность и приводит к ненулевому коду завершения консоли; результаты уже завершённых билдеров сохраняются. Общие исходные данные передаются через DI.

Очистка использует тот же проверенный список, пропускает билдеры без опционального интерфейса и успешно завершается при уже отсутствующих артефактах. Чужие файлы сохраняются; каталоги кешей не очищаются рекурсивно. `app:clean` заменяет `app:cache:clear`, опции выбора каталогов удалены. Обе команды проходят обычную загрузку с доступными картами. Следующий процесс после очистки запускается без этих карт.

Примеры регистрации приведены в [описании API билдера App](https://github.com/componenta/app/blob/main/README.ru.md#билдеры-приложения).

## Публичный API

- `ConsoleCommandRegistryInterface` регистрирует команды и проверяет уникальность класса и имени.
- `ConsoleBootTargetInterface` позволяет загрузчикам добавлять команды в приложение.
- `InputFactoryInterface`, `OutputFactoryInterface` и `IOFactory` предоставляют консольный ввод и вывод.
- `ConfigKey::COMMANDS` содержит ID сервисов команд.
