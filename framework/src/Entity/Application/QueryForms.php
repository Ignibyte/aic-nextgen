<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\DeletedScope;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Output;
use InvalidArgumentException;

/**
 * The query calls of #309, #311 and #329, in a trait so `EntityQuery` keeps
 * its size: the deleted rows a query sees, computed columns, a filter by
 * another table's or query's values, and a query over the query's rows.
 */
trait QueryForms
{
    /**
     * @param list<object> $columns Windows, JsonValues and Similarities, each a column the rows gain.
     *
     * @throws InvalidArgumentException When one is none of these, names a field the type lacks, or is named as a column.
     *
     * @return self The query.
     */
    public function withColumns(array $columns): self
    {
        $this->entityJoins->computed()->add($columns);

        return $this;
    }

    /**
     * @param string          $field       A field of the queried type.
     * @param EntityType      $entityType  Another entity type, or the same.
     * @param string          $sourceField A field of the source whose values the field must be among.
     * @param list<Condition> $conditions  Conditions on the source's rows; its live rows only, for a soft-deleting one.
     *
     * @throws InvalidArgumentException When a field is not its type's, or a condition is malformed.
     *
     * @return self The query, keeping the rows whose field holds a value the source's matching rows hold.
     */
    public function withValuesIn(
        string $field,
        EntityType $entityType,
        string $sourceField,
        array $conditions = [],
    ): self {
        $this->entityConditions->valuesIn($field, $entityType, $sourceField, $conditions);

        return $this;
    }

    /**
     * @param DeletedScope $deletedScope Which rows of a soft-deleting type it sees (#309): all, or the deleted ones.
     *
     * @throws InvalidArgumentException When it asks for the deleted rows of a type that deletes for good.
     *
     * @return self The query.
     */
    public function withDeleted(DeletedScope $deletedScope = DeletedScope::All): self
    {
        $this->entityConditions->scope($deletedScope);

        return $this;
    }

    /**
     * @param string       $field        A field of the queried type.
     * @param DerivedQuery $derivedQuery A query over another query's rows (#329), its order and limit kept.
     * @param string       $column       One of its columns, whose values the field must be among.
     *
     * @throws InvalidArgumentException When the type lacks the field, or the derived query the column.
     *
     * @return self The query, keeping the rows whose field holds a value the derived query's rows hold.
     */
    public function withValuesOf(string $field, DerivedQuery $derivedQuery, string $column): self
    {
        $this->entityConditions->valuesOf($field, $derivedQuery, $column);

        return $this;
    }

    /**
     * @param list<Output> $outputs The columns its rows have, in order; none for every column this query reads.
     *
     * @throws InvalidArgumentException When an output reads a column this query does not, or two have one name.
     *
     * @return DerivedQuery A query over this query's rows (#329): conditions and order on any column, a computed one
     *                      included, and unions with another's.
     */
    public function derived(array $outputs = []): DerivedQuery
    {
        $derivedSource = new DerivedSource($this->selectSql(), $this->selectParams(), $this->entityJoins->outputs());

        return new DerivedQuery($this->database, $derivedSource->project($outputs));
    }

    /**
     * @return string The query's SELECT.
     */
    private function selectSql(): string
    {
        return 'SELECT ' . $this->entityJoins->selected() . ' FROM ' . $this->entityJoins->from()
            . $this->entityConditions->sql() . $this->entityPaging->sql($this->entityType->table);
    }

    /**
     * @return list<string|int> Its values, in the order of their marks.
     */
    private function selectParams(): array
    {
        return [...$this->entityJoins->computed()->params(), ...$this->entityConditions->params()];
    }
}
