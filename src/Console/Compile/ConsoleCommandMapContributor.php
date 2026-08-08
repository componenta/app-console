<?php

declare(strict_types=1);

namespace Componenta\App\Console\Compile;

use Componenta\App\Console\ConfigKey;
use Componenta\App\Discovery\Compile\CompileCacheContributorInterface;

final readonly class ConsoleCommandMapContributor implements CompileCacheContributorInterface
{
    /**
     * @param list<class-string> $classes
     * @return array<string, mixed>
     */
    public function compile(array $classes): array
    {
        $commands = (new ConsoleCommandCompiler())->compile($classes);

        return $commands === []
            ? []
            : [ConfigKey::COMMANDS => $commands];
    }
}
