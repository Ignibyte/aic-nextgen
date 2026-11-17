<?php

declare(strict_types=1);

namespace {namespace}\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use {namespace}\Entity\{class};
use InvalidArgumentException;

use function array_map;
use function array_reverse;

/**
 * The `{table}` rows over the entity store, made by bin/aic make:entity (#123).
 */
final readonly class {class}Repository
{
    /**
     * @param EntityStore $entityStore The entity store.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
    }

    /**
     * @param int $count How many of the newest rows to read.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the count is below 1, or a constructed value is invalid.
     *
     * @return list<{class}> The newest rows, at most that many, oldest first.
     */
    public function latest(int $count): array
    {
        ${variable}Rows = $this->entityStore->query($this->{variable}Type())
            ->orderByDescending('{order}')
            ->limit($count)
            ->rows()
            ->all();

        return array_map(self::{variable}(...), array_reverse(${variable}Rows));
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `{table}` table.
     */
    private function {variable}Type(): EntityType
    {
        return new EntityType('{table}', 'id', [
{fields}            new Field('created_at', FieldType::CreatedAt),
        ]{keyType});
    }

    /**
     * @param Row $row A `{table}` row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return {class} Its entity.
     */
    private static function {variable}(Row $row): {class}
    {
        return new {class}(
{reads}        );
    }
}
