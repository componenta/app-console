<?php

declare(strict_types=1);

namespace Componenta\App\Console;

final class ConfigKey
{
    private function __construct()
    {
    }

    /**
     * Console command entry ids registered by packages or the application.
     *
     * @var string
     */
    public const string COMMANDS = 'console.commands';
    public const string COMMAND_MAP_FILE = 'var/cache/build/commands.php';
}
