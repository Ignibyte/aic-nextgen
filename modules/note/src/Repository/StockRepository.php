<?php

declare(strict_types=1);

namespace App\Note\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Note\Entity\StockItem;
use App\Note\Form\StockEntry;

use function array_map;

/**
 * The stock items: the `stock_items` entity type over the entity store, with
 * a date, a numeric and a boolean field.
 */
final readonly class StockRepository
{
    /**
     * The `stock_items` table.
     */
    private EntityType $entityType;

    /**
     * @param EntityStore $entityStore The entity store.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
        $this->entityType = new EntityType('stock_items', 'id', [
            new Field('name', FieldType::Text),
            new Field('price', FieldType::Numeric),
            new Field('added_on', FieldType::Date),
            new Field('certified', FieldType::Boolean),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<StockItem> Every item, oldest first.
     */
    public function all(): array
    {
        return array_map(self::item(...), $this->entityStore->query($this->entityType)->orderBy('id')->rows());
    }

    /**
     * @param StockEntry $stockEntry A posted item with nothing wrong with it.
     *
     * @throws QueryException When the insert fails.
     */
    public function add(StockEntry $stockEntry): void
    {
        $this->entityStore->insert($this->entityType, [
            'added_on' => $stockEntry->addedOn,
            'certified' => $stockEntry->certified,
            'name' => $stockEntry->name,
            'price' => $stockEntry->price,
        ]);
    }

    /**
     * @param Row $row A `stock_items` row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return StockItem The item.
     */
    private static function item(Row $row): StockItem
    {
        return new StockItem($row->string('name'), $row->string('price'), $row->string('added_on'), $row->bool('certified'));
    }
}
