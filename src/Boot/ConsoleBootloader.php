<?php

declare(strict_types=1);

namespace Componenta\App\Boot;

use Componenta\App\Boot\Target\ConsoleBootTargetInterface;
use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
use Componenta\App\Console\ConsoleCommandRegistryInterface;
use Componenta\App\Scope;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Scope\Scopes;
use Componenta\Reflection\Reflection;
use RuntimeException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Bootloader for console command registration.
 *
 * In production: loads commands from pre-compiled config file.
 * In development: discovers commands via #[AsCommand] attribute.
 */
final class ConsoleBootloader implements BootloaderInterface
{
    use ScopedBootloaderSupport;

    public function __construct(
        private readonly ConsoleCommandRegistryInterface $commands,
    ) {}

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

        if ($context->container->config->environment?->match('APP_ENV', 'production') === true) {
            return;
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
     * @return list<class-string>
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
     * Dev CLI runs `Componenta\App\Discovery\Discovery` in `config/config.php`
     * and the resulting iterator is registered as a shared service in
     * `ContainerFactory`. If neither ran, there is nothing to attribute-
     * scan and we skip silently.
     */
    private function discoveredClasses(BootContext $context): ?iterable
    {
        $c = $context->container;

        return $c->has(ClassIteratorInterface::class)
            ? $c->get(ClassIteratorInterface::class, ClassIteratorInterface::class)
            : null;
    }
}
