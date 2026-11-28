<?php

declare(strict_types=1);

namespace App\Scaffold\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Scaffold\Entity\ScaffoldTask;
use App\Scaffold\Entity\ScaffoldTypes;
use InvalidArgumentException;

use function array_map;
use function array_reverse;

/**
 * The `scaffold_tasks` rows over the entity store, made by bin/aic make:entity (#123); its type is the module's (#328).
 */
final readonly class ScaffoldTaskRepository
{
    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param ScaffoldTypes $scaffoldTypes The module's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private ScaffoldTypes $scaffoldTypes,
    ) {
    }

    /**
     * @param int $count How many of the newest rows to read.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the count is below 1, or a constructed value is invalid.
     *
     * @return list<ScaffoldTask> The newest rows, at most that many, oldest first.
     */
    public function latest(int $count): array
    {
        $scaffoldTaskRows = $this->entityStore->query($this->scaffoldTypes->scaffoldTask())
            ->orderByDescending('id')
            ->limit($count)
            ->rows()
            ->all();

        return array_map(self::scaffoldTask(...), array_reverse($scaffoldTaskRows));
    }

    /**
     * @param Row $row A `scaffold_tasks` row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return ScaffoldTask Its entity.
     */
    private static function scaffoldTask(Row $row): ScaffoldTask
    {
        return new ScaffoldTask(
            $row->string('title'),
            $row->string('due_on'),
            $row->bool('done'),
        );
    }
}
