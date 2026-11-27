<?php

declare(strict_types=1);

namespace Componenta\App\Console\Build;

use Componenta\App\Build\ApplicationBuildRunner;
use Componenta\App\Build\PreparedBuild;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\App\Console\Command\CleanCommand;
use Componenta\Stdlib\PathResolverInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Dispatch build operations before loading the ordinary container and bootloaders. */
final class BuildConsole
{
    /**
     * Return null for ordinary commands. Call this immediately after autoloading.
     * Help and version are handled by Symfony without evaluating build providers.
     */
    public static function run(
        PathResolverInterface $paths,
        ?InputInterface $input = null,
        ?OutputInterface $output = null,
    ): ?int {
        $input ??= new ArgvInput();
        if (!in_array($input->getFirstArgument(), ['app:build', 'app:clean'], true)) {
            return null;
        }

        $application = new Application('Componenta');
        $application->setAutoExit(false);
        $application->addCommands([
            new BuildCommand(static fn (): PreparedBuild => ApplicationBuildRunner::build(
                $paths,
                static fn () => require $paths->resolve('config/config.php'),
            )),
            new CleanCommand(static function () use ($paths): void {
                ApplicationBuildRunner::clean($paths);
            }),
        ]);

        return $application->run($input, $output);
    }

    private function __construct() {}
}
