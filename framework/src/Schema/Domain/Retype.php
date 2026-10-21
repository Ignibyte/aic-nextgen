<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\ColumnType;
use AIC\Schema\Contracts\LogicalType;
use InvalidArgumentException;

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
     * @throws InvalidArgumentException When the new type is an identity.
     */
    public function __construct(
        public Identifier $column,
        public ColumnType $type,
        public string $using,
    ) {
        if ($type->logicalType === LogicalType::BigIdentity) {
            throw new InvalidArgumentException('Column ' . $column->value . ': a column cannot change into an identity.');
        }
    }
}
