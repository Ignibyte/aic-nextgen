<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Routing\Domain\CompiledRoute;
use AIC\Routing\Domain\RoutePatternCompiler;
use Gates\Domain\NativeSiteAnswer;
use Gates\Domain\NativeSiteStep;
use InvalidArgumentException;

use function array_any;
use function explode;
use function sprintf;
use function strtoupper;

/**
 * gate:23's reach (#171): every GET route the app serves is reached by a step,
 * or neither the native comparison nor the HTML lint sees it. A step reaches a
 * route when it is a GET, expects a status below 400, and the path it was sent
 * to, without its query, matches the route as the framework's router matches it.
 */
final readonly class NativeSiteCoverage
{
    /** A status a step must expect less than to count: a refusal proves the route answers, not what it serves. */
    private const int REFUSED = 400;

    /**
     * @param NativeSiteRoutes     $nativeSiteRoutes     The app's GET routes.
     * @param RoutePatternCompiler $routePatternCompiler The router's own pattern compiler.
     */
    public function __construct(
        private NativeSiteRoutes $nativeSiteRoutes = new NativeSiteRoutes(),
        private RoutePatternCompiler $routePatternCompiler = new RoutePatternCompiler(),
    ) {
    }

    /**
     * @param string                 $root    The project's root.
     * @param list<NativeSiteStep>   $steps   The steps.
     * @param list<NativeSiteAnswer> $answers The PHP site's answers, one per step.
     *
     * @return list<string> One line per GET route no step reaches, or one naming an unreadable manifest.
     */
    public function unreached(string $root, array $steps, array $answers): array
    {
        try {
            $routes = $this->nativeSiteRoutes->gets($root);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return ['UNREADABLE ' . $invalidArgumentException->getMessage()];
        }

        $paths = $this->reached($steps, $answers);
        $lines = [];

        foreach ($routes as $route) {
            if (self::matchesAny($this->routePatternCompiler->compile($route), $paths)) {
                continue;
            }

            $fix = 'add a step that reaches it to ' . NativeSiteScript::PATH;
            $lines[] = sprintf('UNREACHED GET %s (%s): %s', $route->path, $route->handler, $fix);
        }

        return $lines;
    }

    /**
     * @param list<NativeSiteStep>   $steps   The steps.
     * @param list<NativeSiteAnswer> $answers The answers, one per step.
     *
     * @return list<string> The path, without its query, of each GET step that expects a status below 400.
     */
    private function reached(array $steps, array $answers): array
    {
        $paths = [];

        foreach ($steps as $index => $step) {
            $answer = $answers[$index] ?? null;

            if (
                strtoupper($step->method) !== 'GET'
                || $step->status >= self::REFUSED
                || !$answer instanceof NativeSiteAnswer
            ) {
                continue;
            }

            $paths[] = explode('?', $answer->path, 2)[0];
        }

        return $paths;
    }

    /**
     * @param CompiledRoute $compiledRoute A route.
     * @param list<string>  $paths         The reached paths.
     *
     * @return bool Whether one of them matches it.
     */
    private static function matchesAny(CompiledRoute $compiledRoute, array $paths): bool
    {
        return array_any($paths, static fn (string $path): bool => $compiledRoute->matches($path));
    }
}
