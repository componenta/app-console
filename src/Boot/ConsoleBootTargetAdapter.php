<?php

declare(strict_types=1);

namespace Componenta\App\Boot;

use Componenta\App\AppInterface;
use Componenta\App\Boot\Target\ConsoleBootTarget;
use Componenta\App\Console\App as ConsoleApp;
use Componenta\App\Scope;
use Componenta\Scope\ScopeInterface;
use LogicException;

final readonly class ConsoleBootTargetAdapter implements BootTargetAdapterInterface
{
    public function supports(ScopeInterface $scope): bool
    {
        return $scope->matches(Scope::CLI);
    }

    public function create(AppInterface $app, ScopeInterface $scope): object
    {
        if (!$app instanceof ConsoleApp) {
            throw new LogicException(sprintf(
                'Scope "%s" expects app %s, %s given.',
                $scope->value,
                ConsoleApp::class,
                $app::class,
            ));
        }

        return new ConsoleBootTarget($app);
    }
}
