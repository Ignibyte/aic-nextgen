<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Application\ConsoleWiring;
use AIC\Module\Application\WebPresentation;
use AIC\Module\Application\WebWiring;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\DuplicateRouteException;
use AIC\Schedule\Domain\Schedule;
use AIC\Schema\Contracts\Migration;
use InvalidArgumentException;
use Override;

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
        {scheduleThrows}     * @return list<Schedule> The modules' schedules.
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
             * @throws InvalidArgumentException When the compiled asset metadata is invalid.
             *
             * @return WebPresentation The compiled template and public asset catalogs.
             */
            #[Override]
            public function presentation(): WebPresentation
            {
                return (new Presentation())->create();
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
        {scheduleThrows}     * @return list<Schedule> The modules' schedules.
             */
            #[Override]
            public function schedules(): array
            {
                return (new ModuleSchedules())->all();
            }
        PHP;

    /**
     * @param PhpClassFile          $phpClassFile          Lays out each file.
     * @param ExceptionDocs         $exceptionDocs         Reads freshly generated aggregator contracts.
     * @param WebPresentationSource $webPresentationSource Writes the inert presentation factory.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private ExceptionDocs $exceptionDocs = new ExceptionDocs(),
        private WebPresentationSource $webPresentationSource = new WebPresentationSource(),
    ) {
    }

    /**
     * @param list<string>          $aggregates The optional aggregators generated (`ModuleHandlers`, …).
     * @param list<string>          $rootless   Those that take no context (`ModuleJobs` when no job reads a root).
     * @param array<string, string> $sources    The freshly generated aggregators.
     *
     * @throws InvalidArgumentException When a generated aggregator declares an unresolved exception type.
     *
     * @return array<string, string> `WebModules` and `ConsoleModules`, by file name.
     */
    public function files(array $aggregates, array $rootless, array $sources): array
    {
        $scheduleTypes = $this->exceptionDocs->source($sources['ModuleSchedules.php'] ?? '');
        $web = [
            WebWiring::class,
            Override::class,
            RouteCollector::class,
            DuplicateRouteException::class,
            Schedule::class,
            WebPresentation::class,
            InvalidArgumentException::class,
            ...$scheduleTypes,
        ];
        $console = [ConsoleWiring::class, Override::class, Migration::class, Schedule::class, ...$scheduleTypes];

        $consoleParts = $this->parts(FacadeParts::CONSOLE, $aggregates, $rootless, $sources);
        $webParts = $this->parts(FacadeParts::WEB, $aggregates, $rootless, $sources);
        $replace = ['{scheduleThrows}' => $this->exceptionDocs->block($scheduleTypes)];

        return [
            'ConsoleModules.php' => $this->facade('ConsoleModules', 'console', $console, strtr(self::CONSOLE, $replace), $consoleParts),
            'Presentation.php' => $this->webPresentationSource->file(),
            'WebModules.php' => $this->facade('WebModules', 'app', $web, strtr(self::WEB, $replace), $webParts),
        ];
    }

    /**
     * @param array<string, array{class-string, string, non-empty-list<class-string>}> $parts      The optional parts.
     * @param list<string>                                                             $aggregates The aggregators generated.
     * @param list<string>                                                             $rootless   Those that take no context.
     * @param array<string, string>                                                    $sources    The freshly generated aggregators.
     *
     * @throws InvalidArgumentException When a generated aggregator declares an unresolved exception type.
     *
     * @return list<array{class-string, string, list<class-string>}> The parts whose aggregator exists, each rootless
     *                                                               one as its context-free sibling.
     */
    private function parts(array $parts, array $aggregates, array $rootless, array $sources): array
    {
        $present = [];

        foreach ($parts as $aggregate => $part) {
            if (!in_array($aggregate, $aggregates, true)) {
                continue;
            }

            $exceptions = $this->exceptionDocs->source($sources[$aggregate . '.php'] ?? '');
            $part = [$part[0], $this->exceptionDocs->append($part[1], $exceptions), [...$part[2], ...$exceptions]];

            $present[] = in_array($aggregate, $rootless, true)
                ? $this->rootless($part)
                : $part;
        }

        return $present;
    }

    /**
     * @param array{class-string, string, non-empty-list<class-string>} $part An optional part that passes its aggregator the
     *                                                                        context.
     *
     * @return array{class-string, string, list<class-string>} Its context-free sibling: the sibling interface, the
     *                                                         method without the context parameter, and the types
     *                                                         without the context; the part itself when it has none
     *                                                         (handlers always read the context, which wraps them).
     */
    private function rootless(array $part): array
    {
        [$interface, $method, $types] = $part;

        if (!array_key_exists($interface, FacadeParts::ROOTLESS)) {
            return $part;
        }

        $context = $types[0];
        $variable = '$' . lcfirst($this->phpClassFile->short($context));
        $method = (string) preg_replace('/\\n\\h+\\* @param [^\\n]+\\n\\h+\\*\\n/', "\n", $method);
        $method = strtr($method, [$this->phpClassFile->short($context) . ' ' . $variable => '', '(' . $variable . ')' => '()']);

        return [FacadeParts::ROOTLESS[$interface], $method, array_values(array_diff($types, [$context]))];
    }

    /**
     * @param string                                                $class    The facade's short name.
     * @param string                                                $kernel   The kernel that takes it, for its docblock.
     * @param non-empty-list<class-string>                          $classes  Its required interface and the types it names.
     * @param string                                                $required Its required methods.
     * @param list<array{class-string, string, list<class-string>}> $parts    Its optional parts.
     *
     * @throws InvalidArgumentException When imported class names conflict.
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
        $docblock = $this->phpClassFile->generated('the module manifests', "the modules' wiring, as the " . $kernel . ' kernel takes it.');

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
    }
}
