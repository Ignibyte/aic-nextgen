<?php

declare(strict_types=1);

namespace {namespace}\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
{uses}use InvalidArgumentException;

use function array_map;
use function array_reverse;

/**
 * The `{table}` rows over the entity store, made by bin/aic make:entity (#123); its type is the module's (#328).
 */
final readonly class {class}Repository
{
    /**
{params}     */
    public function __construct(
        private EntityStore $entityStore,
        private {types} ${typesVariable},
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
        ${variable}Rows = $this->entityStore->query($this->{typesVariable}->{variable}())
            ->orderByDescending('{order}')
            ->limit($count)
            ->rows()
            ->all();

        return array_map(self::{variable}(...), array_reverse(${variable}Rows));
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
