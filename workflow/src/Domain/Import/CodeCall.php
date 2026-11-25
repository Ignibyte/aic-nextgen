<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

use function in_array;

/**
 * A call on the migration's `Expressions` (#318), such as `compare(…)`.
 */
final readonly class CodeCall implements CodeNode
{
    private const array PREDICATES = ['compare', 'isNull', 'oneOf', 'all', 'any', 'not', 'matches'];

    /**
     * @param string         $method    The `Expressions` method.
     * @param list<CodeNode> $arguments Its arguments.
     */
    public function __construct(
        public string $method,
        public array $arguments,
    ) {
    }

    /**
     * @return bool Whether the call makes a `Predicate`; otherwise a `Term`.
     */
    public function isPredicate(): bool
    {
        return in_array($this->method, self::PREDICATES, true);
    }

    /**
     * @param int $index An argument's place, from 0.
     *
     * @return CodeNode|null The argument; null when the call has none there.
     */
    public function argument(int $index): ?CodeNode
    {
        return $this->arguments[$index] ?? null;
    }

    /**
     * @param int $index An argument's place, from 0.
     *
     * @return CodeList|null The argument, when it is a list.
     */
    public function listArgument(int $index): ?CodeList
    {
        $codeNode = $this->argument($index);

        return $codeNode instanceof CodeList
            ? $codeNode
            : null;
    }

    /**
     * @param int $index An argument's place, from 0.
     *
     * @return CodeValue|null The argument, when it is PHP written as it is.
     */
    public function valueArgument(int $index): ?CodeValue
    {
        $codeNode = $this->argument($index);

        return $codeNode instanceof CodeValue
            ? $codeNode
            : null;
    }
}
