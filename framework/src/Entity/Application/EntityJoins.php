<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\EntityJoin;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

use function array_any;
use function explode;
use function implode;
use function in_array;
use function preg_match;

/**
 * A query's joins (#190): each through one of the entity type's reference
 * fields, under a prefix. It writes the query's FROM, every column a row
 * returns (the entity type's, then each joined type's as `prefix_column`),
 * and qualifies a joined field named `prefix.field`.
 */
final class EntityJoins
{
    /**
     * The joins, in the order they were added.
     *
     * @var list<EntityJoin>
     */
    private array $joins = [];

    /**
     * @param EntityType $entityType The entity type queried.
     */
    public function __construct(
        private readonly EntityType $entityType,
    ) {
    }

    /**
     * @param string $field    A Reference field of the entity type.
     * @param string $prefix   The joined table's name in the query, an identifier other than the table's.
     * @param bool   $optional Whether a row without a match stays (a left join).
     *
     * @throws InvalidArgumentException When the field is not a reference, or the prefix is malformed or taken.
     */
    public function add(string $field, string $prefix, bool $optional): void
    {
        $reference = $this->entityType->field($field);
        $references = $reference->references;

        if ($reference->type !== FieldType::Reference || !$references instanceof EntityType) {
            throw new InvalidArgumentException('The ' . $this->entityType->table . ' entity\'s field "' . $field . '" is no reference.');
        }

        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $prefix) !== 1 || $this->taken($prefix)) {
            throw new InvalidArgumentException(
                'A join prefix is an identifier that no other table of the query uses; got "' . $prefix . '".',
            );
        }

        $entityJoin = new EntityJoin($reference, $references, $prefix, $optional);
        $this->joins[] = $entityJoin;
    }

    /**
     * @param string $field A joined field, `prefix.field`.
     *
     * @throws InvalidArgumentException When no join has the prefix, or its entity type has no such column.
     *
     * @return string The column, safe to write into SQL.
     */
    public function column(string $field): string
    {
        [$prefix, $name] = explode('.', $field, 2) + ['', ''];

        foreach ($this->joins as $join) {
            if ($join->prefix === $prefix && in_array($name, $join->entityType->columns(), true)) {
                return $prefix . '.' . $name;
            }
        }

        throw new InvalidArgumentException('The query has no joined field "' . $field . '".');
    }

    /**
     * @return string The table and each join, for a FROM.
     */
    public function from(): string
    {
        $from = $this->entityType->table;

        foreach ($this->joins as $join) {
            $from .= ($join->optional ? ' LEFT JOIN ' : ' INNER JOIN ') . $join->entityType->table . ' AS '
                . $join->prefix . ' ON ' . $join->prefix . '.' . $join->entityType->key . ' = '
                . $this->entityType->table . '.' . $join->field->name;
        }

        return $from;
    }

    /**
     * @return string The columns a row returns: the entity type's, then each joined type's as `prefix_column`.
     */
    public function selected(): string
    {
        $columns = [];

        foreach ($this->entityType->columns() as $column) {
            $columns[] = $this->entityType->table . '.' . $column;
        }

        foreach ($this->joins as $join) {
            foreach ($join->entityType->columns() as $column) {
                $columns[] = $join->prefix . '.' . $column . ' AS ' . $join->prefix . '_' . $column;
            }
        }

        return implode(', ', $columns);
    }

    /**
     * @return bool Whether the query joins any table.
     */
    public function hasAny(): bool
    {
        return $this->joins !== [];
    }

    /**
     * @param string $prefix A prefix asked for.
     *
     * @return bool Whether the table or a join already uses it.
     */
    private function taken(string $prefix): bool
    {
        if ($prefix === $this->entityType->table) {
            return true;
        }

        return array_any($this->joins, static fn (EntityJoin $entityJoin): bool => $entityJoin->prefix === $prefix);
    }
}
