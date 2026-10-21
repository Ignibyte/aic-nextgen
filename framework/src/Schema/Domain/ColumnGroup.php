<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use InvalidArgumentException;

/**
 * The columns of one unique constraint or one index, in order.
 */
final readonly class ColumnGroup
{
    /**
     * @param list<string> $columns The columns' names.
     *
     * @throws InvalidArgumentException When there are none.
     */
    public function __construct(
        public array $columns,
    ) {
        if ($columns === []) {
            throw new InvalidArgumentException('A unique constraint or an index names a column.');
        }
    }
}
