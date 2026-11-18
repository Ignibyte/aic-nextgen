<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\Predicate;
use InvalidArgumentException;

use function array_merge;
use function implode;

/**
 * An index over the rows a predicate covers (#310), unique among them or not.
 */
final readonly class PartialIndex
{
    /**
     * @param ColumnGroup $columnGroup The columns it indexes, in order.
     * @param Predicate   $predicate   The rows it covers.
     * @param bool        $unique      Whether no two covered rows share the columns' values.
     */
    public function __construct(
        public ColumnGroup $columnGroup,
        public Predicate $predicate,
        public bool $unique,
    ) {
    }

    /**
     * @return list<string> Every column it names: those it indexes, then those its predicate reads.
     */
    public function columns(): array
    {
        return array_merge($this->columnGroup->columns, $this->predicate->columns);
    }

    /**
     * @param Identifier $identifier The table it indexes.
     *
     * @throws InvalidArgumentException When the name would be longer than an identifier may be.
     *
     * @return Identifier Its name: the table, the columns, then `unique_idx` or `partial_idx`.
     */
    public function indexName(Identifier $identifier): Identifier
    {
        $kind = $this->unique
            ? 'unique'
            : 'partial';

        return new Identifier($identifier->value . '_' . implode('_', $this->columnGroup->columns) . '_' . $kind . '_idx');
    }
}
