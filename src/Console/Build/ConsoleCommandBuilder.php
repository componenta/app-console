<?php

declare(strict_types=1);

namespace Componenta\App\Console\Build;

use Componenta\App\Build\ApplicationBuilderInterface;
use Componenta\App\Build\BuildContext;
use Componenta\ClassFinder\ClassIteratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;

final readonly class ConsoleCommandBuilder implements ApplicationBuilderInterface
{
    public const string ARTIFACT = 'console.commands';

    public function __construct(private ClassIteratorInterface $classes) {}

    public function build(BuildContext $build): void
    {
        $map = [];
        foreach ($this->classes as $class) {
            $map[$class->fullyQualifiedName] = $class->reflector->getAttributes(AsCommand::class) !== [];
        }
        $build->write(self::ARTIFACT, $map);
    }
}
