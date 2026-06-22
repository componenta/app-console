<?php

declare(strict_types=1);

namespace Componenta\App\Console;

use Componenta\App\AppAdapterInterface;
use Componenta\App\AppInterface;
use Componenta\App\Scope;
use Componenta\Config\ContainerValue;
use Componenta\Scope\ScopeInterface;

final readonly class ConsoleAppAdapter implements AppAdapterInterface
{
    public function supports(ScopeInterface $scope): bool
    {
        return $scope->matches(Scope::CLI);
    }

    public function createApp(ScopeInterface $scope, ContainerValue $container): AppInterface
    {
        return App::createFromContainer($container);
    }
}
