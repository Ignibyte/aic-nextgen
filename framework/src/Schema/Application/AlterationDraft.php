<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnType;
use AIC\Schema\Contracts\TableAlteration;
use AIC\Schema\Domain\Alteration;
use AIC\Schema\Domain\Column;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\Retype;
use InvalidArgumentException;
use LogicException;
use Override;

/**
 * The TableAlteration a migration chains on: it collects the columns to add
 * or change in its ColumnChanges and the constraints and indexes in its
 * ConstraintDraft, and gives the Alteration.
 */
final readonly class AlterationDraft implements TableAlteration
{
    use AddsConstraints;

    /**
     * @param Identifier      $identifier      The existing table's name.
     * @param ColumnChanges   $columnChanges   Collects the columns to add and the type changes.
     * @param ConstraintDraft $constraintDraft Collects the constraints and indexes to add.
     */
    public function __construct(
        private Identifier $identifier,
        private ColumnChanges $columnChanges,
        private ConstraintDraft $constraintDraft,
    ) {
    }

    /**
     * @param string     $name       The new column.
     * @param ColumnType $columnType Its type.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     *
     * @return self The builder.
     */
    #[Override]
    public function addColumn(string $name, ColumnType $columnType): self
    {
        $this->columnChanges->add(new Column(new Identifier($name), $columnType, false));

        return $this;
    }

    /**
     * Lets the column just added hold null.
     *
     * @throws LogicException When no column has been added.
     *
     * @return self The builder.
     */
    #[Override]
    public function nullable(): self
    {
        $this->columnChanges->nullableLast();

        return $this;
    }

    /**
     * @param string     $column     An existing column.
     * @param ColumnType $columnType Its new type.
     * @param string     $using      SQL that converts each value; empty for a plain cast.
     *
     * @throws InvalidArgumentException When the name is not an identifier or the type is an identity.
     *
     * @return self The builder.
     */
    #[Override]
    public function changeType(string $column, ColumnType $columnType, string $using = ''): self
    {
        $this->columnChanges->retype(new Retype(new Identifier($column), $columnType, $using));

        return $this;
    }

    /**
     * @throws InvalidArgumentException When nothing was added or changed.
     *
     * @return Alteration The alteration as built.
     */
    public function alteration(): Alteration
    {
        return new Alteration(
            $this->identifier,
            $this->columnChanges->added(),
            $this->columnChanges->retyped(),
            $this->constraintDraft->constraints(),
        );
    }
}
