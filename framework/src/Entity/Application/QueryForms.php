<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\DeletedScope;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

/**
 * The query calls of #309 and #311, in a trait so `EntityQuery` keeps its
 * size: the deleted rows a query sees, computed columns, and a filter by
 * another table's values.
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
        $this->entityJoins->computeColumns($columns);

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
}
