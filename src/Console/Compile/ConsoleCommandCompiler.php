<?php

declare(strict_types=1);

namespace Componenta\App\Console\Compile;

use Componenta\Reflection\Reflection;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Attribute\AsCommand;

final class ConsoleCommandCompiler
{
    /**
     * @param iterable<class-string> $classes
     * @return list<class-string>
     */
    public function compile(iterable $classes): array
    {
        $commands = [];

        foreach ($classes as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($class);
            } catch (ReflectionException) {
                continue;
            }

            if (!Reflection::hasMetadata($reflection, AsCommand::class)) {
                continue;
            }

            $commands[$reflection->getName()] = true;
        }

        ksort($commands);

        return array_keys($commands);
    }
}
