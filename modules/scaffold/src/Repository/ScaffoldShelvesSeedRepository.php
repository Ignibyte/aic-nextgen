<?php

declare(strict_types=1);

namespace App\Scaffold\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The `scaffold_shelves` rows ScaffoldShelvesSeed saves, made by bin/aic make:seed
 * (#320): each an upsert matched by `name`, which the table must hold
 * unique; `created_at` is set when a row is first saved. The entity store
 * refuses a table that records its events or keeps a state.
 */
final readonly class ScaffoldShelvesSeedRepository
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
        $this->entityStore->upsert($this->scaffoldShelvesType(), $values, 'name');
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `scaffold_shelves` table's seeded fields, and `created_at`, set at insert.
     */
    private function scaffoldShelvesType(): EntityType
    {
        return new EntityType('scaffold_shelves', 'id', [
            new Field('name', FieldType::Text),
            new Field('zone', FieldType::Choice, choices: ['front', 'back']),
            new Field('capacity', FieldType::Integer),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }
}
