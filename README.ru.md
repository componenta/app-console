# Componenta App Console

Интеграция Symfony Console с Componenta: CLI-приложение, целевой объект загрузки, реестр команд и команды обслуживания.

## Установка

~~~bash
composer require componenta/app-console
~~~

Требуется PHP 8.5.13 или новее. Пакет объявляет `Componenta\App\Console\ConfigProvider` в метаданных Composer; `componenta/composer-plugin` добавляет его в сгенерированный список провайдеров.

## Регистрация сервисов

Провайдер регистрирует сервис CLI-приложения в `AppConfigKey::APPLICATIONS`, `ConsoleBootTargetAdapter` в `AppConfigKey::BOOT_TARGET_ADAPTERS` и `ConsoleBootloader` в `AppConfigKey::BOOTLOADERS`. Он регистрирует реестр команд, фабрику диспетчера событий и фабрики BuildCommand и CleanCommand.

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

## Команды подготовки приложения

| Команда | Назначение |
|---|---|
| `app:build` | Собрать конфигурацию из текущих исходников, выполнить билдеры и опубликовать одно поколение. |
| `app:clean` | Снять текущее поколение с публикации без загрузки провайдеров и удаления файлов, используемых работающими процессами. |

Вызывайте `BuildConsole::run($paths)` **до** обычной загрузки контейнера и `Componenta\App\run()`. Стандартный Skeleton уже содержит эту развилку:

~~~php
$paths = new \Componenta\Stdlib\PathResolver(dirname(__DIR__));
$code = \Componenta\App\Console\Build\BuildConsole::run($paths);
if ($code !== null) {
    exit($code);
}
\Componenta\App\run(\Componenta\App\Scope::CLI, $paths);
~~~

Для deployment используйте полные имена `app:build` и `app:clean`. Их справка и версия обрабатываются без загрузки конфигурации приложения. Для остальных команд `BuildConsole` возвращает `null`. Оба сервиса команд остаются зарегистрированы для списка команд и пользовательских CLI. Стандартные фабрики используют `config/config.php`; иной bootstrap задаётся собственной closure команды или прямым вызовом `ApplicationBuildRunner`. Вызов build-команды после обычной загрузки не отменяет уже выполненные bootloader-эффекты.

`ApplicationBuildRunner` выбирает исходный режим до вызова провайдеров и создаёт свежий контейнер, не запуская runtime bootloaders. Новый `#[AsConfig]` и удалённые объявления классов учитываются при первой пересборке без предварительной очистки. Провайдеры выполняются для определения сборки, но целый объект Config и живые сервисы не сериализуются. Структурные входы доменных карт должны соответствовать идентификатору релиза/профиля.

`ConsoleCommandBuilder::build(BuildContext $build)` записывает артефакт `console.commands`. В нём хранится только принадлежность классов к командам. Runtime читает выбранный при bootstrap `PreparedBuild` через `AppConfigKey::PREPARED_BUILD`; конструирование команд, атрибуты, aliases и приоритет явно настроенных команд сохраняются. Недоступная или некорректная необязательная карта использует исходный reflection-путь. Пустая карта допустима.

App владеет публикацией всех артефактов поколения. Ошибка любого билдера оставляет прежнее поколение активным и завершает команду с ошибкой. Для неподдерживаемой необязательной оптимизации выводится причина перехода к исходному режиму. Пустой список билдеров публикует корректное пустое поколение.

Новые аргументы команд: `BuildCommand(Closure(): PreparedBuild)` и `CleanCommand(Closure(): void)`. Удалены per-builder cleaner, аргументы файлов артефактов и `ConfigKey::COMMAND_MAP_FILE`. `ApplicationBuildRunner::clean($paths)` принимает только объект разрешения путей; аргумент окружения удалён. Очистка снимает только `current.json`, повторяема и работает при сломанной конфигурации или ошибках в файле окружения. Удаляйте сохранённые поколения только после остановки использующих их процессов.

`APP_ENV` не включает обязательную сборку. Одинаковый `APP_BUILD_ID` при подготовке и запуске связывает артефакты с неизменяемым релизом/профилем. После структурных изменений исходников или конфигурации пересоберите приложение и перезапустите workers. Политика обязательных артефактов и схемы описаны в App README.

## Публичный API

- `ConsoleCommandRegistryInterface` регистрирует команды и проверяет уникальность класса и имени.
- `ConsoleBootTargetInterface` позволяет загрузчикам добавлять команды в приложение.
- `InputFactoryInterface`, `OutputFactoryInterface` и `IOFactory` предоставляют консольный ввод и вывод.
- `ConfigKey::COMMANDS` содержит ID сервисов команд.
