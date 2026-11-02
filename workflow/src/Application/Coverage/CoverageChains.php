<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use function explode;
use function strpos;
use function substr;

/**
 * Follows a `chains` edge (#232): `Head::first|second|…` names the first
 * receiver's class and the methods called in order. Each method is found on
 * the class the chain has reached, through its ancestors, and the chain
 * moves on to the class that method declares it returns. It stops at a
 * method it cannot find or one that declares no class.
 */
final readonly class CoverageChains
{
    /**
     * @param CoverageGraph $coverageGraph The declarations.
     */
    public function __construct(
        private CoverageGraph $coverageGraph,
    ) {
    }

    /**
     * @param string $target A `chains` edge's target.
     *
     * @return list<string> The full names of the methods it calls, as far as their classes are known.
     */
    public function methods(string $target): array
    {
        $split = strpos($target, '::');

        if ($split === false) {
            return [];
        }

        $class = substr($target, 0, $split);
        $methods = [];

        foreach (explode('|', substr($target, $split + 2)) as $name) {
            $method = $this->coverageGraph->method($class, $name);

            if ($method === '') {
                return $methods;
            }

            $methods[] = $method;
            $class = $this->coverageGraph->returns($method);
        }

        return $methods;
    }
}
