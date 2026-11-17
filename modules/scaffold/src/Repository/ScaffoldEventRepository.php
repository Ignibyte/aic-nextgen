<?php

declare(strict_types=1);

namespace App\Scaffold\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Scaffold\Entity\ScaffoldEvent;
use InvalidArgumentException;

use function array_map;
use function array_reverse;

/**
 * The `scaffold_events` rows over the entity store, made by bin/aic make:entity (#123).
 */
final readonly class ScaffoldEventRepository
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
     * @return list<ScaffoldEvent> The newest rows, at most that many, oldest first.
     */
    public function latest(int $count): array
    {
        $scaffoldEventRows = $this->entityStore->query($this->scaffoldEventType())
            ->orderByDescending('created_at')
            ->limit($count)
            ->rows()
            ->all();

        return array_map(self::scaffoldEvent(...), array_reverse($scaffoldEventRows));
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `scaffold_events` table.
     */
    private function scaffoldEventType(): EntityType
    {
        return new EntityType('scaffold_events', 'id', [
            new Field('label', FieldType::Text),
            new Field('state', FieldType::Choice, choices: ['open', 'shut']),
            new Field('payload', FieldType::Json),
            new Field('happened_at', FieldType::Timestamp),
            new Field('source', FieldType::Uuid),
            new Field('created_at', FieldType::CreatedAt),
        ], keyType: FieldType::Uuid);
    }

    /**
     * @param Row $row A `scaffold_events` row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return ScaffoldEvent Its entity.
     */
    private static function scaffoldEvent(Row $row): ScaffoldEvent
    {
        return new ScaffoldEvent(
            $row->string('label'),
            $row->string('state'),
            $row->string('payload'),
            $row->string('happened_at'),
            $row->string('source'),
        );
    }
}
