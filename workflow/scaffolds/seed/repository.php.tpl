<?php

declare(strict_types=1);

namespace {namespace}\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The `{table}` rows {seed} saves, made by bin/aic make:seed
 * (#320): each an upsert matched by `{unique}`, which the table must hold
 * unique; `created_at` is set when a row is first saved. The entity store
 * refuses a table that records its events or keeps a state.
 */
final readonly class {class}
{
    /**
     * @param EntityStore $entityStore The entity store.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
    }

    /**
     * @param array<string, string|int|bool|null> $values One row, by column.
     *
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws QueryException           When the write fails.
     */
    public function save(array $values): void
    {
        $this->entityStore->upsert($this->{type}(), $values, '{unique}');
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `{table}` table's seeded fields, and `created_at`, set at insert.
     */
    private function {type}(): EntityType
    {
        return new EntityType('{table}', 'id', [
{fields}            new Field('created_at', FieldType::CreatedAt),
        ]);
    }
}
