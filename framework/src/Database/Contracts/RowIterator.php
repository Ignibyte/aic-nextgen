<?php

declare(strict_types=1);

namespace AIC\Database\Contracts;

use Iterator;
use Override;

use function count;

/**
 * One pass over a `Rows`, each element typed as a `Row` (#201): the compiled
 * site types a loop variable by `current()`, which it cannot do from a
 * docblock's `list<Row>`.
 *
 * @template-implements Iterator<int, Row>
 */
final class RowIterator implements Iterator
{
    /**
     * Where the pass is.
     */
    private int $position = 0;

    /**
     * @param list<Row> $rows The rows, in order.
     */
    public function __construct(
        private readonly array $rows,
    ) {
    }

    /**
     * @return Row The row at the current position.
     */
    #[Override]
    public function current(): Row
    {
        return $this->rows[$this->position];
    }

    /**
     * @return int The current position.
     */
    #[Override]
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Moves to the next row.
     */
    #[Override]
    public function next(): void
    {
        $this->position += 1;
    }

    /**
     * Moves back to the first row.
     */
    #[Override]
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @return bool Whether there is a row at the current position.
     */
    #[Override]
    public function valid(): bool
    {
        return $this->position < count($this->rows);
    }
}
