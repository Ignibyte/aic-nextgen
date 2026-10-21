<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

/**
 * One column of a table.
 */
final readonly class Column
{
    /**
     * @param Identifier  $name     The column's name.
     * @param LogicalType $type     Its type.
     * @param int         $length   The longest value of a String column, or a Numeric column's precision; 0 for the other types.
     * @param int         $scale    A Numeric column's digits after the point; 0 for the other types.
     * @param bool        $nullable Whether it may hold null.
     */
    public function __construct(
        public Identifier $name,
        public LogicalType $type,
        public int $length,
        public int $scale,
        public bool $nullable,
    ) {
    }

    /**
     * @return self The same column, allowed to hold null.
     */
    public function asNullable(): self
    {
        return new self($this->name, $this->type, $this->length, $this->scale, true);
    }
}
