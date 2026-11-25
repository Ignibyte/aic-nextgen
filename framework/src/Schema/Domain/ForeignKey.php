<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\OnDelete;
use InvalidArgumentException;

use function count;

/**
 * One column, or several together (#344), that refer to another table's, and
 * what happens to its rows when the row they refer to is deleted.
 */
final readonly class ForeignKey
{
    /**
     * @param list<string> $columns        The referring columns, in order.
     * @param Identifier   $table          The table they refer to.
     * @param list<string> $foreignColumns The columns they refer to there, in the same order: a primary key, or a unique
     *                                     constraint's.
     * @param OnDelete     $onDelete       What a delete there does here.
     *
     * @throws InvalidArgumentException When there are no columns, or the two lists differ in length.
     */
    public function __construct(
        public array $columns,
        public Identifier $table,
        public array $foreignColumns,
        public OnDelete $onDelete,
    ) {
        if ($columns === [] || count($columns) !== count($foreignColumns)) {
            throw new InvalidArgumentException('A foreign key refers to one column for each of its own, one or more.');
        }
    }
}
