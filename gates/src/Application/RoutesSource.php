<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\RouteMethod;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\DuplicateRouteException;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

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
     * @throws InvalidArgumentException When its class would not fit the gates.
     *
     * @return string Its `<M>Routes` class file.
     */
    public function module(ModuleDefinition $moduleDefinition): string
    {
        $classes = [RouteCollector::class, DuplicateRouteException::class];
        $chain = '';

        foreach ($moduleDefinition->declared(ModuleRoute::class) as $moduleRoute) {
            $classes[] = $moduleRoute->handler;
            $chain .= $this->call($moduleRoute);
        }

        $from = 'modules/' . $moduleDefinition->name . '/' . $moduleDefinition->name . '.module.php';
        $body = strtr(self::MODULE_TEMPLATE, ['{class}' => $moduleDefinition->studlyName() . 'Routes', '{chain}' => $chain]);

        return $this->phpClassFile->file('App\\Generated', $classes, [], $this->phpClassFile->generated($from, 'its routes.'), $body);
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
}
