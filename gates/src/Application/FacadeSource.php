<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\ComponentTemplates;
use AIC\Container\Contracts\ServiceRegistry;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Module\Application\CommandWiring;
use AIC\Module\Application\ConsoleContext;
use AIC\Module\Application\ConsoleWiring;
use AIC\Module\Application\HandlerContext;
use AIC\Module\Application\HandlerWiring;
use AIC\Module\Application\JobWiring;
use AIC\Module\Application\MiddlewareWiring;
use AIC\Module\Application\PluginWiring;
use AIC\Module\Application\RootlessCommandWiring;
use AIC\Module\Application\RootlessJobWiring;
use AIC\Module\Application\RootlessMiddlewareWiring;
use AIC\Module\Application\WebWiring;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\DuplicateRouteException;
use AIC\Schedule\Domain\Schedule;
use AIC\Schema\Contracts\Migration;
use Override;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_diff;
use function array_key_exists;
use function array_values;
use function implode;
use function in_array;
use function lcfirst;
use function preg_replace;
use function sort;
use function strtr;

use const SORT_FLAG_CASE;
use const SORT_STRING;

/**
 * Writes the two generated facades the kernels take (#93): `WebModules` for
 * `AppKernel` and `ConsoleModules` for `ConsoleKernel`. Each implements its
 * kernel's required interface and, of the optional ones, only those some
 * module needs, so no method ever leaves its context unread.
 */
final readonly class FacadeSource
{
    /**
     * Each optional web interface's method, by the aggregator it calls.
     */
    private const array WEB_PARTS = [
        'ModuleHandlers' => [HandlerWiring::class, <<<'PHP'
                /**
                 * @param HandlerContext $handlerContext The roots and the kind wrappers.
                 *
                 * @return array<string, RequestHandlerInterface> The modules' handlers by id.
                 */
                #[Override]
                public function handlers(HandlerContext $handlerContext): array
                {
                    return (new ModuleHandlers($handlerContext))->byId();
                }
            PHP, [HandlerContext::class, RequestHandlerInterface::class]],
        'ModuleMiddleware' => [MiddlewareWiring::class, <<<'PHP'
                /**
                 * @param HandlerContext $handlerContext The roots.
                 *
                 * @return list<MiddlewareInterface> The modules' middleware after the session, in build order.
                 */
                #[Override]
                public function afterSession(HandlerContext $handlerContext): array
                {
                    return (new ModuleMiddleware($handlerContext))->afterSession();
                }
            PHP, [HandlerContext::class, MiddlewareInterface::class]],
        'ModuleProvider' => [PluginWiring::class, <<<'PHP'
                /**
                 * @param ServiceRegistry $serviceRegistry Where the modules' blocks and plugins go.
                 */
                #[Override]
                public function register(ServiceRegistry $serviceRegistry): void
                {
                    (new ModuleProvider())->register($serviceRegistry);
                }
            PHP, [ServiceRegistry::class]],
    ];

    /**
     * Each optional console interface's method, by the aggregator it calls.
     */
    private const array CONSOLE_PARTS = [
        'ModuleCommands' => [CommandWiring::class, <<<'PHP'
                /**
                 * @param ConsoleContext $consoleContext The console's roots.
                 *
                 * @return array<string, Command> The modules' commands by name.
                 */
                #[Override]
                public function commands(ConsoleContext $consoleContext): array
                {
                    return (new ModuleCommands($consoleContext))->byName();
                }
            PHP, [ConsoleContext::class, Command::class]],
        'ModuleJobs' => [JobWiring::class, <<<'PHP'
                /**
                 * @param ConsoleContext $consoleContext The console's roots.
                 *
                 * @return array<string, Job> The modules' jobs by name.
                 */
                #[Override]
                public function jobs(ConsoleContext $consoleContext): array
                {
                    return (new ModuleJobs($consoleContext))->byName();
                }
            PHP, [ConsoleContext::class, Job::class]],
        'ModuleProvider' => [PluginWiring::class, <<<'PHP'
                /**
                 * @param ServiceRegistry $serviceRegistry Where the modules' blocks and plugins go.
                 */
                #[Override]
                public function register(ServiceRegistry $serviceRegistry): void
                {
                    (new ModuleProvider())->register($serviceRegistry);
                }
            PHP, [ServiceRegistry::class]],
    ];

    /**
     * The context-free sibling of each optional interface whose aggregator may read no root (#93).
     */
    private const array ROOTLESS = [
        CommandWiring::class => RootlessCommandWiring::class,
        JobWiring::class => RootlessJobWiring::class,
        MiddlewareWiring::class => RootlessMiddlewareWiring::class,
    ];

    /**
     * `WebModules`' required methods.
     */
    private const string WEB = <<<'PHP'
            /**
             * @param RouteCollector $routeCollector The routes so far.
             *
             * @throws DuplicateRouteException When a module's route repeats one already declared.
             *
             * @return RouteCollector The collector with the modules' routes.
             */
            #[Override]
            public function routes(RouteCollector $routeCollector): RouteCollector
            {
                return (new ModuleRoutes())->add($routeCollector);
            }

            /**
             * @return list<Schedule> The modules' schedules.
             */
            #[Override]
            public function schedules(): array
            {
                return (new ModuleSchedules())->all();
            }

            /**
             * @return list<string> The modules' protected path prefixes.
             */
            #[Override]
            public function protectedPrefixes(): array
            {
                return (new ModulePrefixes())->all();
            }

            /**
             * @return ComponentTemplates The modules' component templates, ending at the framework's atoms (#98).
             */
            #[Override]
            public function templates(): ComponentTemplates
            {
                return new ModuleTemplates();
            }
        PHP;

    /**
     * `ConsoleModules`' required methods.
     */
    private const string CONSOLE = <<<'PHP'
            /**
             * @return list<Migration> The modules' migrations, in build order.
             */
            #[Override]
            public function migrations(): array
            {
                return (new ModuleMigrations())->all();
            }

            /**
             * @return list<Schedule> The modules' schedules.
             */
            #[Override]
            public function schedules(): array
            {
                return (new ModuleSchedules())->all();
            }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out each file.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param list<string> $aggregates The optional aggregators generated (`ModuleHandlers`, …).
     * @param list<string> $rootless   Those that take no context (`ModuleJobs` when no job reads a root).
     *
     * @return array<string, string> `WebModules` and `ConsoleModules`, by file name.
     */
    public function files(array $aggregates, array $rootless): array
    {
        $web = [
            WebWiring::class,
            Override::class,
            RouteCollector::class,
            DuplicateRouteException::class,
            Schedule::class,
            ComponentTemplates::class,
        ];
        $console = [ConsoleWiring::class, Override::class, Migration::class, Schedule::class];

        $consoleParts = $this->parts(self::CONSOLE_PARTS, $aggregates, $rootless);
        $webParts = $this->parts(self::WEB_PARTS, $aggregates, $rootless);

        return [
            'ConsoleModules.php' => $this->facade('ConsoleModules', 'the console kernel', $console, self::CONSOLE, $consoleParts),
            'WebModules.php' => $this->facade('WebModules', 'the app kernel', $web, self::WEB, $webParts),
        ];
    }

    /**
     * @param array<string, array{class-string, string, list<class-string>}> $parts      The optional parts.
     * @param list<string>                                                   $aggregates The aggregators generated.
     * @param list<string>                                                   $rootless   Those that take no context.
     *
     * @return list<array{class-string, string, list<class-string>}> The parts whose aggregator exists, each rootless
     *                                                               one as its context-free sibling.
     */
    private function parts(array $parts, array $aggregates, array $rootless): array
    {
        $present = [];

        foreach ($parts as $aggregate => $part) {
            if (!in_array($aggregate, $aggregates, true)) {
                continue;
            }

            $present[] = in_array($aggregate, $rootless, true)
                ? $this->rootless($part)
                : $part;
        }

        return $present;
    }

    /**
     * @param array{class-string, string, list<class-string>} $part An optional part that passes its aggregator the
     *                                                              context.
     *
     * @return array{class-string, string, list<class-string>} Its context-free sibling: the sibling interface, the
     *                                                         method without the context parameter, and the types
     *                                                         without the context; the part itself when it has none
     *                                                         (handlers always read the context, which wraps them).
     */
    private function rootless(array $part): array
    {
        [$interface, $method, $types] = $part;

        if (!array_key_exists($interface, self::ROOTLESS)) {
            return $part;
        }

        $context = $types[0];
        $variable = '$' . lcfirst($this->phpClassFile->short($context));
        $method = (string) preg_replace('/\\n\\h+\\* @param [^\\n]+\\n\\h+\\*\\n/', "\n", $method);
        $method = strtr($method, [$this->phpClassFile->short($context) . ' ' . $variable => '', '(' . $variable . ')' => '()']);

        return [self::ROOTLESS[$interface], $method, array_values(array_diff($types, [$context]))];
    }

    /**
     * @param string                                                $class    The facade's short name.
     * @param string                                                $kernel   The kernel that takes it, for its docblock.
     * @param list<class-string>                                    $classes  Its required interface and the types it names.
     * @param string                                                $required Its required methods.
     * @param list<array{class-string, string, list<class-string>}> $parts    Its optional parts.
     *
     * @return string The class file.
     */
    private function facade(string $class, string $kernel, array $classes, string $required, array $parts): string
    {
        $interfaces = [$this->phpClassFile->short($classes[0])];
        $methods = [$required];

        foreach ($parts as [$interface, $method, $types]) {
            $interfaces[] = $this->phpClassFile->short($interface);
            $methods[] = $method;
            $classes = [...$classes, $interface, ...$types];
        }

        sort($interfaces, SORT_STRING | SORT_FLAG_CASE);
        $declaration = 'final readonly class ' . $class . ' implements ' . implode(', ', $interfaces);
        $body = $declaration . "\n{\n" . implode("\n\n", $methods) . "\n}";
        $docblock = $this->phpClassFile->generated('the module manifests', "the modules' wiring, as " . $kernel . ' takes it.');

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
    }
}
