<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\TableBuilder;
use AIC\Schema\Domain\Column;
use AIC\Schema\Domain\Identifier;
use InvalidArgumentException;
use LogicException;
use Override;

/**
 * The TableBuilder a migration chains on: each call goes to its TableParts
 * (columns, primary key) or its ConstraintDraft (the rest).
 */
final readonly class TableDraft implements TableBuilder
{
    use AddsConstraints;

    /**
     * @param TableParts      $tableParts      Collects the columns and the primary key.
     * @param ConstraintDraft $constraintDraft Collects the unique constraints, indexes and foreign keys.
     * @param ColumnTypes     $columnTypes     Makes each column's type, with its checks.
     */
    public function __construct(
        private TableParts $tableParts,
        private ConstraintDraft $constraintDraft,
        private ColumnTypes $columnTypes,
    ) {
    }

    /**
     * @param string $name The column.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The builder.
     */
    #[Override]
    public function identity(string $name): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->identity(), false));

        return $this;
    }

    /**
     * @param string $name   The column.
     * @param int    $length Its longest value, in characters.
     *
     * @throws InvalidArgumentException When the length is not positive.
     *
     * @return self The builder.
     */
    #[Override]
    public function string(string $name, int $length): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->string($length), false));

        return $this;
    }

    /**
     * @param string $name The column.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The builder.
     */
    #[Override]
    public function text(string $name): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->text(), false));

        return $this;
    }

    /**
     * @param string $name The column, a whole number.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The builder.
     */
    #[Override]
    public function integer(string $name): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->integer(), false));

        return $this;
    }

    /**
     * @param string $name The column, a 64-bit whole number.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The builder.
     */
    #[Override]
    public function bigInteger(string $name): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->bigInteger(), false));

        return $this;
    }

    /**
     * @param string $name The column, a point in time with its time zone.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The builder.
     */
    #[Override]
    public function timestampTz(string $name): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->timestampTz(), false));

        return $this;
    }

    /**
     * @param string $name The column, a calendar date with no time or zone.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The builder.
     */
    #[Override]
    public function date(string $name): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->date(), false));

        return $this;
    }

    /**
     * @param string $name      The column, an exact decimal.
     * @param int    $precision Its digits in all, 1 to 1000.
     * @param int    $scale     Its digits after the point, 0 to the precision.
     *
     * @throws InvalidArgumentException When the precision or the scale is out of range.
     *
     * @return self The builder.
     */
    #[Override]
    public function numeric(string $name, int $precision, int $scale): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->numeric($precision, $scale), false));

        return $this;
    }

    /**
     * @param string $name The column, true or false.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The builder.
     */
    #[Override]
    public function boolean(string $name): self
    {
        $this->tableParts->add(new Column(new Identifier($name), $this->columnTypes->boolean(), false));

        return $this;
    }

    /**
     * Lets the last column added hold null.
     *
     * @throws LogicException When no column has been added.
     *
     * @return self The builder.
     */
    #[Override]
    public function nullable(): self
    {
        $this->tableParts->nullableLast();

        return $this;
    }

    /**
     * @param list<string> $columns The primary key's columns.
     *
     * @return self The builder.
     */
    #[Override]
    public function primary(array $columns): self
    {
        $this->tableParts->primary($columns);

        return $this;
    }

    /**
     * @param string   $column        The referring column, on this table.
     * @param string   $table         The table it refers to.
     * @param string   $foreignColumn The column it refers to there.
     * @param OnDelete $onDelete      What deleting the row it refers to does here.
     *
     * @throws InvalidArgumentException When a name is not an identifier.
     *
     * @return self The builder.
     */
    #[Override]
    public function foreignKey(string $column, string $table, string $foreignColumn, OnDelete $onDelete): self
    {
        $this->constraintDraft->foreignKey($column, $table, $foreignColumn, $onDelete);

        return $this;
    }
}
