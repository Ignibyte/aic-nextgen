<?php

declare(strict_types=1);

namespace App\Note\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Note\Entity\StockDay;
use InvalidArgumentException;

use function array_map;

/**
 * The stock report (#191): the `stock_days` view, read through a read-only
 * entity type.
 */
final readonly class StockReportRepository
{
    /**
     * The `stock_days` view: per day, how many items came in and their worth.
     */
    private EntityType $entityType;

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @throws InvalidArgumentException When the type is declared wrongly.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
        $this->entityType = new EntityType('stock_days', 'added_on', [
            new Field('items', FieldType::Integer),
            new Field('worth', FieldType::Numeric),
        ], true);
    }

    /**
     * @param int    $count How many of the latest days to read.
     * @param string $day   One day, `YYYY-MM-DD`; empty for the latest.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the count is below 1, or a constructed value is invalid.
     *
     * @return list<StockDay> The latest days stock came in, or that day, newest first.
     */
    public function days(int $count, string $day): array
    {
        $entityQuery = $this->entityStore->query($this->entityType);

        if ($day !== '') {
            $entityQuery->where('added_on', $day);
        }

        $rows = $entityQuery->orderByDescending('added_on')->limit($count)->rows()->all();

        return array_map(
            static fn (Row $row): StockDay => new StockDay($row->string('added_on'), $row->int('items'), $row->string('worth')),
            $rows,
        );
    }
}
