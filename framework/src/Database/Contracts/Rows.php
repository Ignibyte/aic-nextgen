<?php

declare(strict_types=1);

namespace AIC\Database\Contracts;

use IteratorAggregate;
use Override;

/**
 * A query's rows, in order (#201). A loop over it is typed as `Row` in the
 * compiled site too, and each loop gets its own `RowIterator`, so two loops
 * over one `Rows` never share a cursor. Array functions and `count()` take `all()`.
 *
 * @template-implements IteratorAggregate<int, Row>
 */
final readonly class Rows implements IteratorAggregate
{
    /**
     * @param list<Row> $rows The rows, in order.
     */
    public function __construct(
        private array $rows,
    ) {
    }

    /**
     * @return RowIterator A fresh pass over the rows.
     */
    #[Override]
    public function getIterator(): RowIterator
    {
        return new RowIterator($this->rows);
    }

    /**
     * @return list<Row> The rows, in order, for array functions.
     */
    public function all(): array
    {
        return $this->rows;
    }

    /**
     * @return Row|null The first row; null when there is none.
     */
    public function first(): ?Row
    {
        return $this->rows[0] ?? null;
    }
}
