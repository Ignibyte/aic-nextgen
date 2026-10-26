<?php

declare(strict_types=1);

namespace App\Scaffold\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Scaffold\Entity\ScaffoldTask;
use InvalidArgumentException;

use function array_map;
use function array_reverse;

/**
 * The `scaffold_tasks` rows over the entity store, made by bin/aic make:entity (#123).
 */
final readonly class ScaffoldTaskRepository
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
     * @return list<ScaffoldTask> The newest rows, at most that many, oldest first.
     */
    public function latest(int $count): array
    {
        $scaffoldTaskRows = $this->entityStore->query($this->scaffoldTaskType())
            ->orderByDescending('id')
            ->limit($count)
            ->rows();

        return array_map(self::scaffoldTask(...), array_reverse($scaffoldTaskRows));
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `scaffold_tasks` table.
     */
    private function scaffoldTaskType(): EntityType
    {
        return new EntityType('scaffold_tasks', 'id', [
            new Field('title', FieldType::Text),
            new Field('due_on', FieldType::Date),
            new Field('done', FieldType::Boolean),
            new Field('created_at', FieldType::CreatedAt),
        ]);
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
