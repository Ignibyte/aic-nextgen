<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\OnDelete;

/**
 * One column that refers to a column of another table, and what happens to
 * its rows when the row it refers to is deleted.
 */
final readonly class ForeignKey
{
    /**
     * @param Identifier $column        The referring column.
     * @param Identifier $table         The table it refers to.
     * @param Identifier $foreignColumn The column it refers to, a primary key or unique column there.
     * @param OnDelete   $onDelete      What a delete there does here.
     */
    public function __construct(
        public Identifier $column,
        public Identifier $table,
        public Identifier $foreignColumn,
        public OnDelete $onDelete,
    ) {
    }
}
