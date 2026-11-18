<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\Direction;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\FieldType;
use AIC\Entity\Domain\JsonValue;
use AIC\Entity\Domain\Similarity;
use AIC\Entity\Domain\Window;
use AIC\Entity\Domain\WindowFunction;
use InvalidArgumentException;

use function implode;
use function in_array;

/**
 * Renders a computed column (#311) of a query on an entity type: a window
 * over partition fields, a Json field's text at a path, or a text field's
 * similarity to a value. Every field is the type's own, qualified by its
 * table; a path and a compared text are bound.
 */
final readonly class ComputedSql
{
    /**
     * @param EntityType $entityType The entity type queried.
     */
    public function __construct(
        private EntityType $entityType,
    ) {
    }

    /**
     * @param Window $window A window.
     *
     * @throws InvalidArgumentException When it names a field the type lacks, or its alias is a column.
     *
     * @return string `, <function> OVER (…) AS <alias>`.
     */
    public function windowColumn(Window $window): string
    {
        return ', ' . $this->window($window) . ' AS ' . $this->alias($window->alias);
    }

    /**
     * @param JsonValue $jsonValue A JSON value.
     *
     * @throws InvalidArgumentException When its field is not a Json field, or its alias is a column.
     *
     * @return string `, <field> #>> '{<path>}' AS <alias>`: the path written as a literal, its keys being letters,
     *                digits and `_` (`JsonValue` checks them), since the compiled site's PDO sends a `text[]`
     *                parameter in a form Postgres misreads (#330).
     */
    public function jsonColumn(JsonValue $jsonValue): string
    {
        $path = "'{" . implode(',', $jsonValue->path) . "}'";

        return ', ' . $this->typedField($jsonValue->field, FieldType::Json) . ' #>> ' . $path . ' AS ' . $this->alias($jsonValue->alias);
    }

    /**
     * @param Similarity $similarity A similarity.
     *
     * @throws InvalidArgumentException When its field is not a Text field, or its alias is a column.
     *
     * @return string `, similarity(<field>, ?)::text AS <alias>`, its text bound.
     */
    public function similarityColumn(Similarity $similarity): string
    {
        $field = $this->typedField($similarity->field, FieldType::Text);

        return ', similarity(' . $field . ', ?)::text AS ' . $this->alias($similarity->alias);
    }

    /**
     * @param Window $window A window.
     *
     * @throws InvalidArgumentException When it names a field the type lacks.
     *
     * @return string Its function over its partition and order.
     */
    private function window(Window $window): string
    {
        $function = match ($window->windowFunction) {
            WindowFunction::RowNumber => 'row_number()',
            WindowFunction::Rank => 'rank()',
            WindowFunction::DenseRank => 'dense_rank()',
        };
        $partition = [];

        foreach ($window->partitionBy as $field) {
            $partition[] = $this->anyField($field);
        }

        $partitionBy = $partition === []
            ? ''
            : 'PARTITION BY ' . implode(', ', $partition) . ' ';
        $direction = $window->direction === Direction::Descending
            ? ' DESC'
            : ' ASC';

        return $function . ' OVER (' . $partitionBy . 'ORDER BY ' . $this->anyField($window->orderBy) . $direction . ')';
    }

    /**
     * @param string $field A field of the type, or its key.
     *
     * @throws InvalidArgumentException When the type has no such field.
     *
     * @return string Its column, qualified by the table.
     */
    private function anyField(string $field): string
    {
        if ($field !== $this->entityType->key) {
            $this->entityType->field($field);
        }

        return $this->entityType->table . '.' . $field;
    }

    /**
     * @param string    $field     A field of the type.
     * @param FieldType $fieldType The type it must be.
     *
     * @throws InvalidArgumentException When the type has no such field, or it is of another type.
     *
     * @return string Its column, qualified by the table.
     */
    private function typedField(string $field, FieldType $fieldType): string
    {
        if ($this->entityType->field($field)->type !== $fieldType) {
            throw new InvalidArgumentException(
                'The ' . $this->entityType->table . ' entity\'s field "' . $field . '" is not of the type the column reads.',
            );
        }

        return $this->entityType->table . '.' . $field;
    }

    /**
     * @param string $alias A computed column's name.
     *
     * @throws InvalidArgumentException When a column of the type has it.
     *
     * @return string It.
     */
    private function alias(string $alias): string
    {
        if (in_array($alias, $this->entityType->columns(), true)) {
            throw new InvalidArgumentException(
                'A computed column is named apart from the ' . $this->entityType->table . " entity's columns.",
            );
        }

        return $alias;
    }
}
