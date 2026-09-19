<?php

declare(strict_types=1);

namespace Componenta\App\Console\Build;

use Componenta\App\Build\ApplicationBuilderInterface;
use Componenta\App\Build\ApplicationBuildCleanerInterface;
use Componenta\App\Build\PhpMapFile;
use Componenta\ClassFinder\ClassIteratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;

final readonly class ConsoleCommandBuilder implements ApplicationBuilderInterface, ApplicationBuildCleanerInterface
{
    public function __construct(private ClassIteratorInterface $classes, private string $file) {}

    public function clean(): void
    {
        PhpMapFile::remove($this->file);
    }

    public function build(): void
    {
        $map = [];
        foreach ($this->classes as $class) {
            $map[$class->fullyQualifiedName] = $class->reflector->getAttributes(AsCommand::class) !== [];
        }
        PhpMapFile::write($this->file, $map);
    }
}
