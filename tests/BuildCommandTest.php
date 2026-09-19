<?php

declare(strict_types=1);

use Componenta\App\Build\ApplicationBuilderInterface;
use Componenta\App\Build\ApplicationBuildCleanerInterface;
use Componenta\App\Config\ConfigDefinition;
use Componenta\App\Config\ConfigFactory;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\App\ConfigProvider as AppConfigProvider;
use Componenta\App\Console\ConfigProvider as ConsoleConfigProvider;
use Componenta\App\Console\InputFactoryInterface;
use Componenta\App\Console\IOFactory;
use Componenta\App\Console\OutputFactoryInterface;
use Componenta\App\Runner;
use Componenta\App\Scope;
use Componenta\Config\Config;
use Componenta\Config\ConfigKey;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\Handler\CliErrorHandlerInterface;
use Componenta\Stdlib\PathResolver;
use Componenta\Stdlib\PathResolverInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @param array<string, mixed> $input
 * @param callable(): array<array-key, mixed> $provider
 */
function buildCommandTestContainer(
    array $input,
    BufferedOutput $output,
    CliErrorHandlerInterface $errors,
    callable $provider,
    string $environment = 'production',
): ContainerValue {
    $io = new IOFactory(
        new class($input) implements InputFactoryInterface {
            public function __construct(private array $input) {}
            public function createInput(): InputInterface
            {
                $input = new ArrayInput($this->input);
                $input->setInteractive(false);

                return $input;
            }
        },
        new class($output) implements OutputFactoryInterface {
            public function __construct(private BufferedOutput $output) {}
            public function createOutput(): OutputInterface { return $this->output; }
        },
    );
    $paths = new PathResolver(sys_get_temp_dir() . '/componenta_build_' . bin2hex(random_bytes(8)));
    $result = ConfigFactory::create(
        paths: $paths,
        definition: new ConfigDefinition(providers: [
            new AppConfigProvider(),
            new ConsoleConfigProvider(),
            $provider,
            static fn (): array => [
                ConfigKey::DEPENDENCIES => [
                    ConfigKey::SERVICES => [
                        IOFactory::class => $io,
                        CliErrorHandlerInterface::class => $errors,
                    ],
                ],
            ],
        ]),
        environment: new Environment(['APP_ENV' => $environment]),
    );

    return (new ContainerFactory())->create($result->composition->config, $result->composition->dependencies);
}

it('does not construct builders or request their discovery source when listing commands or showing help', function (array $input): void {
    $created = 0;
    $output = new BufferedOutput();
    $errors = $this->createMock(CliErrorHandlerInterface::class);
    $errors->expects($this->never())->method('handle');
    $container = buildCommandTestContainer($input, $output, $errors, static function () use (&$created): array {
        return [
            AppConfigKey::BUILDERS => ['test.builder'],
            ConfigKey::DEPENDENCIES => [
                ConfigKey::FACTORIES => [
                    'test.builder' => static function (ContainerValue $container) use (&$created): mixed {
                        $created++;

                        return $container->get('build.discovery.source');
                    },
                    'build.discovery.source' => static function (): never {
                        throw new LogicException('Build discovery must remain deferred.');
                    },
                ],
            ],
        ];
    });

    expect(Runner::run(Scope::CLI, $container))->toBe(0)
        ->and($created)->toBe(0)
        ->and($output->fetch())->toContain($input['command_name'] ?? ($input['command'] === 'list' ? 'app:clean' : $input['command']));
})->with([
    'list' => [['command' => 'list']],
    'help command' => [['command' => 'help', 'command_name' => 'app:build']],
    'help option' => [['command' => 'app:build', '--help' => true]],
    'clean help command' => [['command' => 'help', 'command_name' => 'app:clean']],
    'clean help option' => [['command' => 'app:clean', '--help' => true]],
]);

it('runs build or clean through the normal runtime without artifacts or a second Config', function (string $command, string $environment, string $message): void {
    $created = 0;
    $executedWith = [];
    $providerCalls = 0;
    $output = new BufferedOutput();
    $errors = $this->createMock(CliErrorHandlerInterface::class);
    $errors->expects($this->never())->method('handle');
    $container = buildCommandTestContainer(
        ['command' => $command],
        $output,
        $errors,
        static function () use (&$created, &$executedWith, &$providerCalls): array {
            $providerCalls++;

            return [
                AppConfigKey::BUILDERS => ['test.builder'],
                ConfigKey::DEPENDENCIES => [
                    ConfigKey::FACTORIES => [
                        'test.builder' => static function (ContainerValue $container) use (&$created, &$executedWith): ApplicationBuilderInterface {
                            $created++;
                            $action = static function (string $operation) use ($container, &$executedWith): void {
                                $executedWith[] = [$operation, $container->config];
                            };

                            return new class($action) implements ApplicationBuilderInterface, ApplicationBuildCleanerInterface {
                                public function __construct(private Closure $action) {}
                                public function build(): void { ($this->action)('app:build'); }
                                public function clean(): void { ($this->action)('app:clean'); }
                            };
                        },
                    ],
                ],
            ];
        },
        $environment,
    );

    expect($created)->toBe(0)
        ->and(is_dir($container->get(PathResolverInterface::class)->baseDir))->toBeFalse()
        ->and(Runner::run(Scope::CLI, $container))->toBe(0)
        ->and($created)->toBe(1)
        ->and($providerCalls)->toBe(1)
        ->and($executedWith)->toBe([[$command, $container->config]])
        ->and($container->get(Config::class))->toBe($container->config)
        ->and($container->config->environment->get('APP_ENV'))->toBe($environment)
        ->and($output->fetch())->toContain($message)
        ->and(is_dir($container->get(PathResolverInterface::class)->baseDir))->toBeFalse();
})->with([
    ['app:build', 'development', 'Application build completed.'],
    ['app:build', 'production', 'Application build completed.'],
    ['app:clean', 'development', 'Application build artifacts cleaned.'],
    ['app:clean', 'production', 'Application build artifacts cleaned.'],
]);

it('succeeds without any registered builders', function (string $command, string $message): void {
    $output = new BufferedOutput();
    $errors = $this->createMock(CliErrorHandlerInterface::class);
    $errors->expects($this->never())->method('handle');
    $container = buildCommandTestContainer(
        ['command' => $command],
        $output,
        $errors,
        static fn (): array => [],
    );

    expect(Runner::run(Scope::CLI, $container))->toBe(0)
        ->and($output->fetch())->toContain($message);
})->with([
    ['app:build', 'Application build completed.'],
    ['app:clean', 'Application build artifacts cleaned.'],
]);

it('reports a builder failure through the existing console error boundary with a nonzero exit code', function (string $command): void {
    $failure = new DomainException('Build failed');
    $output = new BufferedOutput();
    $errors = $this->createMock(CliErrorHandlerInterface::class);
    $errors->expects($this->once())->method('handle')->with(
        $this->identicalTo($failure),
        $this->isInstanceOf(CliErrorContextInterface::class),
    );
    $container = buildCommandTestContainer(
        ['command' => $command],
        $output,
        $errors,
        static fn (): array => [
            AppConfigKey::BUILDERS => ['failing.builder'],
            ConfigKey::DEPENDENCIES => [
                ConfigKey::SERVICES => [
                    'failing.builder' => new class($failure) implements ApplicationBuilderInterface, ApplicationBuildCleanerInterface {
                        public function __construct(private DomainException $failure) {}
                        public function build(): void { throw $this->failure; }
                        public function clean(): void { throw $this->failure; }
                    },
                ],
            ],
        ],
    );

    expect(Runner::run(Scope::CLI, $container))->toBe(1)
        ->and($output->fetch())->not->toContain('Application build completed.', 'Application build artifacts cleaned.');
})->with(['app:build', 'app:clean']);
