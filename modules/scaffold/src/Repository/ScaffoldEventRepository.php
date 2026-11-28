<?php

declare(strict_types=1);

namespace App\Scaffold\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Scaffold\Entity\ScaffoldEvent;
use App\Scaffold\Entity\ScaffoldTypes;
use InvalidArgumentException;

use function array_map;
use function array_reverse;

/**
 * The `scaffold_events` rows over the entity store, made by bin/aic make:entity (#123); its type is the module's (#328).
 */
final readonly class ScaffoldEventRepository
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
     * @return list<ScaffoldEvent> The newest rows, at most that many, oldest first.
     */
    public function latest(int $count): array
    {
        $scaffoldEventRows = $this->entityStore->query($this->scaffoldTypes->scaffoldEvent())
            ->orderByDescending('created_at')
            ->limit($count)
            ->rows()
            ->all();

        return array_map(self::scaffoldEvent(...), array_reverse($scaffoldEventRows));
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
