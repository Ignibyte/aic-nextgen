<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\TableBuilder;
use AIC\Schema\Domain\Column;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\LogicalType;
use AIC\Schema\Domain\Table;
use InvalidArgumentException;
use LogicException;
use Override;

use function array_pop;

/**
 * The TableBuilder a migration chains on: it collects the columns and the
 * primary key, its ConstraintDraft the rest, and gives the Table.
 */
final class TableDraft implements TableBuilder
{
    /**
     * The columns so far, in order.
     *
     * @var list<object>
     */
    private array $columns = [];

    /**
     * The primary key's columns.
     *
     * @var list<string>
     */
    private array $primaryKey = [];

    /**
     * @param Identifier      $identifier      The table's name.
     * @param ConstraintDraft $constraintDraft Collects its unique constraints, indexes and foreign keys.
     */
    public function __construct(
        private readonly Identifier $identifier,
        private readonly ConstraintDraft $constraintDraft,
    ) {
    }

    /**
     * @param string $name The column.
     *
     * @return self The builder.
     */
    #[Override]
    public function identity(string $name): self
    {
        return $this->add(new Column(new Identifier($name), LogicalType::BigIdentity, 0, false));
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
        if ($length < 1) {
            throw new InvalidArgumentException('Column ' . $name . ': a string column has a positive length.');
        }

        return $this->add(new Column(new Identifier($name), LogicalType::String, $length, false));
    }

    /**
     * @param string $name The column.
     *
     * @return self The builder.
     */
    #[Override]
    public function text(string $name): self
    {
        return $this->add(new Column(new Identifier($name), LogicalType::Text, 0, false));
    }

    /**
     * @param string $name The column, a whole number.
     *
     * @return self The builder.
     */
    #[Override]
    public function integer(string $name): self
    {
        return $this->add(new Column(new Identifier($name), LogicalType::Integer, 0, false));
    }

    /**
     * @param string $name The column, a 64-bit whole number.
     *
     * @return self The builder.
     */
    #[Override]
    public function bigInteger(string $name): self
    {
        return $this->add(new Column(new Identifier($name), LogicalType::BigInteger, 0, false));
    }

    /**
     * @param string $name The column, a point in time with its time zone.
     *
     * @return self The builder.
     */
    #[Override]
    public function timestampTz(string $name): self
    {
        return $this->add(new Column(new Identifier($name), LogicalType::TimestampTz, 0, false));
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
        $last = array_pop($this->columns);

        if (!$last instanceof Column) {
            throw new LogicException('nullable() follows a column.');
        }

        return $this->add($last->asNullable());
    }

    /**
     * @param list<string> $columns The primary key's columns.
     *
     * @return self The builder.
     */
    #[Override]
    public function primary(array $columns): self
    {
        $this->primaryKey = $columns;

        return $this;
    }

    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows share.
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return self The builder.
     */
    #[Override]
    public function unique(array $columns): self
    {
        $this->constraintDraft->unique($columns);

        return $this;
    }

    /**
     * @param list<string> $columns The columns to index, in order.
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return self The builder.
     */
    #[Override]
    public function index(array $columns): self
    {
        $this->constraintDraft->index($columns);

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

    /**
     * @throws InvalidArgumentException When a key, constraint or index names a column the table lacks.
     *
     * @return Table The table as built.
     */
    public function table(): Table
    {
        return new Table($this->identifier, $this->columns, $this->primaryKey, $this->constraintDraft->constraints());
    }

    /**
     * @param Column $column The next column.
     *
     * @return self The builder.
     */
    private function add(Column $column): self
    {
        $this->columns[] = $column;

        return $this;
    }
}
