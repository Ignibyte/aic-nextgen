<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;

use function array_key_exists;
use function array_shift;
use function in_array;

/**
 * The declarations the coverage report walks (#232): each class-like and
 * method by name, and each class-like's parents (what it extends and
 * implements). It finds a method on a class through its ancestors, as PHP
 * does.
 */
final readonly class CoverageGraph
{
    /**
     * @var array<string, CodeSymbol> Each class-like and method, by full name.
     */
    private array $symbols;

    /**
     * @var array<string, list<string>> Each class-like's parents, by name.
     */
    private array $parents;

    /**
     * @param list<CodeSymbol> $declarations Every class-like and method.
     * @param list<CodeEdge>   $edges        Every edge; the `extends` and `implements` ones are read.
     */
    public function __construct(array $declarations, array $edges)
    {
        $symbols = [];
        $parents = [];

        foreach ($declarations as $declaration) {
            $symbols[$declaration->name] = $declaration;
        }

        foreach ($edges as $edge) {
            $parents = in_array($edge->kind, ['extends', 'implements'], true)
                ? [...$parents, $edge->source => [...$parents[$edge->source] ?? [], $edge->target]]
                : $parents;
        }

        $this->symbols = $symbols;
        $this->parents = $parents;
    }

    /**
     * @return list<CodeSymbol> Every class-like and method.
     */
    public function symbols(): array
    {
        $symbols = [];

        foreach ($this->symbols as $symbol) {
            $symbols[] = $symbol;
        }

        return $symbols;
    }

    /**
     * @param string $class A class-like.
     *
     * @return list<string> It and every class-like above it, nearest first, each once.
     */
    public function ancestors(string $class): array
    {
        $seen = [];
        $queue = [$class];

        while ($queue !== []) {
            $next = array_shift($queue);

            if (in_array($next, $seen, true)) {
                continue;
            }

            $seen[] = $next;
            $queue = [...$queue, ...$this->parents[$next] ?? []];
        }

        return $seen;
    }

    /**
     * @param string $class  A class-like.
     * @param string $method A method's name.
     *
     * @return string The full name of the method it has by that name, declared on it or above it; empty when none.
     */
    public function method(string $class, string $method): string
    {
        foreach ($this->ancestors($class) as $ancestor) {
            if (array_key_exists($ancestor . '::' . $method, $this->symbols)) {
                return $ancestor . '::' . $method;
            }
        }

        return '';
    }

    /**
     * @param string $method A method's full name.
     *
     * @return string Its declared return class; empty when it declares none or is not indexed.
     */
    public function returns(string $method): string
    {
        return $this->symbols[$method]->returns ?? '';
    }
}
