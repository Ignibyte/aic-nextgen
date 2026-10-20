<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

use function count;
use function preg_match;
use function preg_replace;

/**
 * A route with its path pattern compiled to a regex, one capture group per
 * parameter, in order.
 */
final readonly class CompiledRoute
{
    /**
     * @param Route        $route      The route.
     * @param string       $regex      The anchored regex its path pattern compiles to.
     * @param list<string> $parameters The parameter names, in group order.
     */
    public function __construct(
        public Route $route,
        public string $regex,
        public array $parameters,
    ) {
    }

    /**
     * @param string $path A request path.
     *
     * @return bool Whether the path fits the pattern.
     */
    public function matches(string $path): bool
    {
        return preg_match($this->regex, $path) === 1;
    }

    /**
     * @return int How many parameters the pattern has; fewer is more specific.
     */
    public function parameterCount(): int
    {
        return count($this->parameters);
    }

    /**
     * Each parameter is read as its numbered group through `preg_replace`, which
     * gives a string with no match array for elephc to type.
     *
     * @param string $path A request path that fits the pattern.
     *
     * @return array<string, string> The parameters by name.
     */
    public function parametersOf(string $path): array
    {
        $parameters = [];

        foreach ($this->parameters as $index => $name) {
            $parameters[$name] = preg_replace($this->regex, '$' . ($index + 1), $path) ?? '';
        }

        return $parameters;
    }
}
