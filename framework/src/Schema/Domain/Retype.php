<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\ColumnType;
use AIC\Schema\Contracts\LogicalType;
use AIC\Schema\Contracts\Term;
use InvalidArgumentException;

use function in_array;

/**
 * A new type for a column an earlier migration created (#86). Its nullability
 * stays as it was.
 */
final readonly class Retype
{
    /**
     * @param Identifier $column The column.
     * @param ColumnType $type   Its new type.
     * @param string     $using  The SQL expression that converts each value, when the database cannot cast it
     *                           itself; empty for none. The migration's author writes it, never a visitor.
     *
     * @throws InvalidArgumentException When the new type is a key, a choice, a generated column (#310) or has a default
     *                                  (#308), which only a
     *                                  new column can be.
     */
    public function __construct(
        public Identifier $column,
        public ColumnType $type,
        public string $using,
    ) {
        $onlyNew = [LogicalType::BigIdentity, LogicalType::UuidKey, LogicalType::Choice];

        if (
            in_array($type->logicalType, $onlyNew, true)
            || $type->generatedAs instanceof Term
            || $type->defaultTo instanceof Term
        ) {
            throw new InvalidArgumentException(
                'Column ' . $column->value . ': a column cannot change into a key, a choice, a generated column or a default.',
            );
        }
    }
}
