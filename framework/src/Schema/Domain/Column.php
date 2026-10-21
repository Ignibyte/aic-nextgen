<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\ColumnType;

/**
 * One column of a table.
 */
final readonly class Column
{
    /**
     * @param Identifier $name     The column's name.
     * @param ColumnType $type     Its type.
     * @param bool       $nullable Whether it may hold null.
     */
    public function __construct(
        public Identifier $name,
        public ColumnType $type,
        public bool $nullable,
    ) {
    }

    /**
     * @return self The same column, allowed to hold null.
     */
    public function asNullable(): self
    {
        return new self($this->name, $this->type, true);
    }
}
