<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * A list filter that takes one of a few values (#324), such as a choice
 * column's or `yes` and `no` for a boolean: the one chosen, or none for any.
 */
final readonly class ListChoice
{
    /**
     * @param string                 $value  The value chosen; empty for any.
     * @param non-empty-list<string> $values Every value it may take, in order.
     */
    public function __construct(
        public string $value,
        public array $values,
    ) {
    }

    /**
     * @return non-empty-list<string> The values a row may hold: the one chosen, or every one when none is.
     */
    public function matches(): array
    {
        return $this->value === ''
            ? $this->values
            : [$this->value];
    }
}
