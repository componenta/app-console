<?php

declare(strict_types=1);

namespace Componenta\App\Boot;

use Componenta\App\Boot\Target\ConsoleBootTargetInterface;
use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
use Componenta\App\Console\ConsoleCommandRegistryInterface;
use Componenta\App\Scope;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Reflection\Reflection;
use Componenta\Scope\Scopes;
use RuntimeException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Bootloader for console command registration.
 *
 * Explicit and discovered commands follow the same registration path in every
 * environment. Production discovery may be backed by a verified class snapshot.
 */
final class ConsoleBootloader implements BootloaderInterface
{
    use ScopedBootloaderSupport;

    public function __construct(
        private readonly ConsoleCommandRegistryInterface $commands,
    ) {
    }

    public Scopes $scopes {
        get => Scopes::of(Scope::CLI);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function boot(BootContext $context): void
    {
        $app = $context->target(ConsoleBootTargetInterface::class);

        foreach ($this->configuredCommandIds($context) as $entryId) {
            $command = $context->container->get($entryId, Command::class);

            $this->commands->register($app, $command);
        }

        $discovered = $this->discoveredClasses($context);

        if ($discovered === null) {
            return;
        }

        foreach ($discovered as $class) {
            if ($this->commands->hasClass($class->fullyQualifiedName)) {
                continue;
            }

            $asCommand = Reflection::getFirstMetadata($class->reflector, AsCommand::class);

            if ($asCommand === null) {
                continue;
            }

            $command = $context->container->get($class->fullyQualifiedName, Command::class);

            $command->setName($asCommand->name);

            if ($asCommand->description !== null) {
                $command->setDescription($asCommand->description);
            }

            if ($asCommand->help !== null) {
                $command->setHelp($asCommand->help);
            }

            if ($asCommand->usages !== []) {
                foreach ($asCommand->usages as $usage) {
                    $command->addUsage($usage);
                }
            }

            $this->commands->register($app, $command);
        }
    }

    /**
     * @return list<non-empty-string>
     */
    private function configuredCommandIds(BootContext $context): array
    {
        $commands = $context->container->config->array(ConsoleConfigKey::COMMANDS, []);
        $seen = [];
        $result = [];

        foreach ($commands as $entryId) {
            if (!is_string($entryId) || $entryId === '') {
                throw new RuntimeException('Console command entry id must be a non-empty string.');
            }

            if (isset($seen[$entryId])) {
                continue;
            }

            $seen[$entryId] = true;
            $result[] = $entryId;
        }

        return $result;
    }

    /**
     * ConfigFactory registers the source iterator or verified production
     * snapshot as a shared dependency. With no discovery definition there is
     * nothing to attribute-scan and registration is skipped.
     */
    private function discoveredClasses(BootContext $context): ?ClassIteratorInterface
    {
        $c = $context->container;

        return $c->has(ClassIteratorInterface::class)
            ? $c->get(ClassIteratorInterface::class, ClassIteratorInterface::class)
            : null;
    }
}
