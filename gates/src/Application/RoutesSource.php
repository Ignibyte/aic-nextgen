<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\RouteMethod;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\DuplicateRouteException;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_key_exists;
use function array_slice;
use function count;
use function implode;
use function strtr;

/**
 * Writes the generated routes (#92): one `<M>Routes` per module with routes,
 * chaining its routes onto the collector, and `App\Generated\ModuleRoutes`,
 * which hands the kernel's collector to each module's in build order, in
 * chained parts (`ChainSource`).
 */
final readonly class RoutesSource
{
    /**
     * The most modules one part chains: its two fixed types and the next part leave 12 of PHPMD's 15.
     */
    private const int PART_SIZE = 12;

    /**
     * How many controllers one module routes part may name: with the collector and its exception, PHPMD's coupling
     * of 15 (#243).
     */
    private const int HANDLERS = 13;

    /**
     * A module's routes, `{class}` and `{chain}` standing for its name and the route calls.
     */
    private const string MODULE_TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
            /**
             * @param RouteCollector $routeCollector The routes so far.
             *
             * @throws DuplicateRouteException When one of the module's routes repeats one already declared.
             *
             * @return RouteCollector The collector, with the module's routes after the others.
             */
            public function add(RouteCollector $routeCollector): RouteCollector
            {
                return $routeCollector{chain};
            }
        }
        PHP;

    /**
     * One part of `ModuleRoutes`, `{class}` and `{body}` standing for its name and its method's body.
     */
    private const string PART_TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
            /**
             * @param RouteCollector $routeCollector The routes so far.
             *
             * @throws DuplicateRouteException When a module's route repeats one already declared.
             *
             * @return RouteCollector The collector, with these modules' routes after the others.
             */
            public function add(RouteCollector $routeCollector): RouteCollector
            {
        {body}
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out each file.
     * @param ChainSource  $chainSource  Splits the aggregator into parts.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private ChainSource $chainSource = new ChainSource(),
    ) {
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module with routes.
     *
     * @throws InvalidArgumentException When one of its classes would not fit the gates.
     *
     * @return array<string, string> Its routes' classes by kind suffix: `Routes`, then `RoutesPart2` and on when its
     *                               controllers do not fit one class (#243), each adding its share; `ModuleRoutes`
     *                               calls them in order, as it calls handler parts.
     */
    public function module(ModuleDefinition $moduleDefinition): array
    {
        $files = [];

        foreach (self::parts($moduleDefinition->declared(ModuleRoute::class)) as $index => $moduleRoutes) {
            $suffix = $index === 0
                ? 'Routes'
                : 'RoutesPart' . ($index + 1);
            $files[$suffix] = $this->part($moduleDefinition, $moduleDefinition->studlyName() . $suffix, $moduleRoutes);
        }

        return $files;
    }

    /**
     * @param list<string> $moduleClasses The modules' `<M>Routes` classes, in build order.
     *
     * @throws InvalidArgumentException Never: each part fits by its size.
     *
     * @return array<string, string> `ModuleRoutes` and its parts, by file name.
     */
    public function aggregate(array $moduleClasses): array
    {
        $files = [];
        $docblock = $this->phpClassFile->generated('the module manifests', "the modules' routes, in build order.");

        foreach ($this->chainSource->parts('ModuleRoutes', $moduleClasses, self::PART_SIZE, self::PART_SIZE) as $part) {
            $calls = $this->chainSource->calls($part);
            $classes = [RouteCollector::class, DuplicateRouteException::class, ...$this->chainSource->qualified($calls)];
            $body = strtr(self::PART_TEMPLATE, ['{class}' => $part['class'], '{body}' => $this->body($calls)]);
            $files[$part['class'] . '.php'] = $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
        }

        return $files;
    }

    /**
     * @param ModuleDefinition  $moduleDefinition The module.
     * @param string            $class            The part's class.
     * @param list<ModuleRoute> $moduleRoutes     Its routes.
     *
     * @throws InvalidArgumentException When the class would not fit the gates.
     *
     * @return string The part's class file.
     */
    private function part(ModuleDefinition $moduleDefinition, string $class, array $moduleRoutes): string
    {
        $classes = [RouteCollector::class, DuplicateRouteException::class];
        $chain = '';

        foreach ($moduleRoutes as $moduleRoute) {
            $classes[] = $moduleRoute->handler;
            $chain .= $this->call($moduleRoute);
        }

        $from = 'modules/' . $moduleDefinition->name . '/' . $moduleDefinition->name . '.module.php';
        $body = strtr(self::MODULE_TEMPLATE, ['{class}' => $class, '{chain}' => $chain]);

        return $this->phpClassFile->file('App\\Generated', $classes, [], $this->phpClassFile->generated($from, 'its routes.'), $body);
    }

    /**
     * @param list<string> $calls The route classes this part chains, then the part after it.
     *
     * @return string The method's body: each adds its routes, and the last call is returned.
     */
    private function body(array $calls): string
    {
        if ($calls === []) {
            return '        return $routeCollector;';
        }

        $lines = [];

        foreach (array_slice($calls, 0, -1) as $call) {
            $lines[] = '        $routeCollector = (new ' . $call . '())->add($routeCollector);';
        }

        $return = '        return (new ' . $calls[count($calls) - 1] . '())->add($routeCollector);';

        return implode("\n", $lines === [] ? [$return] : [...$lines, '', $return]);
    }

    /**
     * @param ModuleRoute $moduleRoute A route.
     *
     * @return string Its call on the chain, on a line of its own.
     */
    private function call(ModuleRoute $moduleRoute): string
    {
        $method = match ($moduleRoute->method) {
            RouteMethod::Get => 'get',
            RouteMethod::Post => 'post',
            RouteMethod::Put => 'put',
            RouteMethod::Patch => 'patch',
            RouteMethod::Delete => 'delete',
        };
        $handler = $this->phpClassFile->short($moduleRoute->handler);

        return "\n            ->" . $method . "('" . $moduleRoute->path . "', " . $handler . '::class)';
    }

    /**
     * @param list<ModuleRoute> $moduleRoutes A module's routes, in order.
     *
     * @return list<list<ModuleRoute>> They in order, in parts that each name at most `HANDLERS` controllers.
     */
    private static function parts(array $moduleRoutes): array
    {
        $parts = [];
        $part = [];
        $handlers = [];

        foreach ($moduleRoutes as $moduleRoute) {
            $fits = array_key_exists($moduleRoute->handler, $handlers) || count($handlers) < self::HANDLERS;
            [$parts, $part, $handlers] = $fits ? [$parts, $part, $handlers] : [[...$parts, $part], [], []];
            $part[] = $moduleRoute;
            $handlers[$moduleRoute->handler] = true;
        }

        return [...$parts, $part];
    }
}
