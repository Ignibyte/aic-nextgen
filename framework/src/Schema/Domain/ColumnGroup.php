<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use InvalidArgumentException;

use function implode;

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

    /**
     * @param Identifier $identifier The table it indexes.
     *
     * @throws InvalidArgumentException When the name would be longer than an identifier may be.
     *
     * @return Identifier The index's name: the table, the columns, then `idx`, joined by underscores.
     */
    public function indexName(Identifier $identifier): Identifier
    {
        return new Identifier($identifier->value . '_' . implode('_', $this->columns) . '_idx');
    }
}
