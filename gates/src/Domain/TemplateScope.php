<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_merge;
use function array_pop;
use function in_array;

/**
 * The variables a template may read at one point of the walk (#96): the
 * component variable its typing line names, and the loop variables of each
 * enclosing `for` and `foreach`, which leave scope with their loop. The walk
 * pushes and pops frames as it enters and leaves loops, so this value
 * changes: it is the one mutable class of the validator.
 */
final class TemplateScope
{
    /**
     * @var list<list<string>> The loop frames, innermost last.
     */
    private array $frames = [];

    /**
     * @param string $component The component variable's name, without `$`.
     */
    public function __construct(
        public readonly string $component,
    ) {
    }

    /**
     * @param list<string> $names The variables a loop binds.
     */
    public function push(array $names): void
    {
        $this->frames[] = $names;
    }

    /**
     * Drop the innermost loop's variables.
     */
    public function pop(): void
    {
        array_pop($this->frames);
    }

    /**
     * @param string $name A variable's name, without `$`.
     *
     * @return bool Whether it is the component variable or a loop variable in scope.
     */
    public function has(string $name): bool
    {
        return $name === $this->component || in_array($name, array_merge([], ...$this->frames), true);
    }
}
