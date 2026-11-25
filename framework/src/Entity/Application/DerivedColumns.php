<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

use function in_array;

/**
 * The columns of a query's rows, in order, each with a name and a type
 * (#329): Integer, Uuid, Numeric, Boolean, Date, Timestamp or Text, what two
 * queries of a union must agree on.
 */
final class DerivedColumns
{
    /**
     * The columns' names, in order.
     *
     * @var list<string>
     */
    private array $names = [];

    /**
     * Their types, in the order of their names.
     *
     * @var list<FieldType>
     */
    private array $types = [];

    /**
     * @param EntityType $entityType An entity type whose columns a query reads.
     * @param string     $prefix     What each name starts with: empty, or a join's `prefix_`.
     *
     * @throws InvalidArgumentException When a name is taken.
     */
    public function addType(EntityType $entityType, string $prefix): void
    {
        foreach ($entityType->columns() as $column) {
            $type = $column === $entityType->key
                ? $entityType->keyType
                : $this->read($entityType->field($column));
            $this->add($prefix . $column, $type);
        }
    }

    /**
     * @param string    $name      A column's name.
     * @param FieldType $fieldType Its type.
     *
     * @throws InvalidArgumentException When the name is taken.
     */
    public function add(string $name, FieldType $fieldType): void
    {
        if (in_array($name, $this->names, true)) {
            throw new InvalidArgumentException('Two columns of the query are named "' . $name . '".');
        }

        $this->names[] = $name;
        $this->types[] = $fieldType;
    }

    /**
     * @return list<string> Their names, in order.
     */
    public function names(): array
    {
        return $this->names;
    }

    /**
     * @param string $name A column's name.
     *
     * @throws InvalidArgumentException When there is no such column.
     *
     * @return FieldType Its type.
     */
    public function type(string $name): FieldType
    {
        foreach ($this->names as $index => $candidate) {
            if ($candidate === $name) {
                return $this->types[$index] ?? FieldType::Text;
            }
        }

        throw new InvalidArgumentException('The query reads no column "' . $name . '".');
    }

    /**
     * @param self $derivedColumns Another query's columns.
     *
     * @throws InvalidArgumentException When the two differ in a name, an order or a type.
     */
    public function align(self $derivedColumns): void
    {
        if ($this->names !== $derivedColumns->names()) {
            throw new InvalidArgumentException('A union joins queries whose columns have the same names in the same order.');
        }

        foreach ($this->names as $name) {
            if ($this->type($name) !== $derivedColumns->type($name)) {
                throw new InvalidArgumentException('A union joins queries whose columns have the same types; "' . $name . '" differs.');
            }
        }
    }

    /**
     * @param Field $field A field.
     *
     * @return FieldType What its column reads as: a stamp a Timestamp, a choice or a Json document Text, a reference
     *                   its target's key type.
     */
    private function read(Field $field): FieldType
    {
        $references = $field->references;

        return match ($field->type) {
            FieldType::CreatedAt, FieldType::UpdatedAt => FieldType::Timestamp,
            FieldType::Choice, FieldType::Json => FieldType::Text,
            FieldType::Reference => $references instanceof EntityType ? $references->keyType : FieldType::Integer,
            FieldType::Text, FieldType::Date, FieldType::Numeric, FieldType::Boolean, FieldType::Integer, FieldType::Uuid,
            FieldType::Timestamp => $field->type,
        };
    }
}
