<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Domain\Column;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\Table;
use InvalidArgumentException;
use LogicException;

use function array_pop;

/**
 * What a TableDraft has collected so far: the table's name, its columns in
 * order, its primary key and its constraints. SchemaPlan keeps it and makes
 * the Table from it.
 */
final class TableParts
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
     * @param ConstraintDraft $constraintDraft Its unique constraints, indexes and foreign keys.
     */
    public function __construct(
        private readonly Identifier $identifier,
        private readonly ConstraintDraft $constraintDraft,
    ) {
    }

    /**
     * @param Column $column The next column.
     */
    public function add(Column $column): void
    {
        $this->columns[] = $column;
    }

    /**
     * Lets the last column added hold null.
     *
     * @throws LogicException When no column has been added.
     */
    public function nullableLast(): void
    {
        $last = array_pop($this->columns);

        if (!$last instanceof Column) {
            throw new LogicException('nullable() follows a column.');
        }

        $this->columns[] = $last->asNullable();
    }

    /**
     * @param list<string> $columns The primary key's columns.
     */
    public function primary(array $columns): void
    {
        $this->primaryKey = $columns;
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
}
