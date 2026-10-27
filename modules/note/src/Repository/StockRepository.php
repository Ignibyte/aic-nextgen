<?php

declare(strict_types=1);

namespace App\Note\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Note\Entity\StockItem;
use App\Note\Entity\StockTotals;
use App\Note\Form\StockEntry;
use App\Note\Form\StockQuery;
use InvalidArgumentException;
use Throwable;

use function array_map;
use function array_reverse;

/**
 * The stock items: the `stock_items` entity type over the entity store, with
 * a date, a numeric, a boolean, a nullable text and integer, and an updated-at
 * field; listed by a search, a lowest price and a page, added up, and saved
 * over by SKU (#184).
 */
final readonly class StockRepository
{
    /**
     * The `stock_items` table.
     */
    private EntityType $entityType;

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
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
            new Field('sku', FieldType::Text, true),
            new Field('quantity', FieldType::Integer, true),
            new Field('updated_at', FieldType::UpdatedAt, true),
        ]);
    }

    /**
     * @param int        $count      How many of the newest items to read, a page's worth.
     * @param StockQuery $stockQuery What the list asks for: a name search, a lowest price, a page.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the count is below 1, or a constructed value is invalid.
     *
     * @return list<StockItem> The page's items, newest last, at most that many.
     */
    public function latest(int $count, StockQuery $stockQuery): array
    {
        $rows = $this->matching($stockQuery)->orderByDescending('id')->limit($count)->offset(($stockQuery->page - 1) * $count)->rows();

        return array_map(self::item(...), array_reverse($rows));
    }

    /**
     * @param StockQuery $stockQuery What the list asks for; its page does not apply.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return StockTotals How many items match, what they are worth, the dearest price and their SKUs.
     */
    public function totals(StockQuery $stockQuery): StockTotals
    {
        $entityTotals = $this->matching($stockQuery)->totals();

        return new StockTotals(
            $entityTotals->count(),
            $entityTotals->sum('price'),
            $entityTotals->max('price'),
            $entityTotals->values('sku'),
        );
    }

    /**
     * @param StockEntry $stockEntry A posted item with nothing wrong with it. With a SKU it saves over the item that
     *                               has it, which keeps the date it first came in.
     *
     * @throws Throwable When the item cannot be stored; the transaction is rolled back.
     */
    public function save(StockEntry $stockEntry): void
    {
        $values = [
            'added_on' => $stockEntry->addedOn,
            'certified' => $stockEntry->certified,
            'name' => $stockEntry->name,
            'price' => $stockEntry->price,
            'quantity' => $stockEntry->quantity === '' ? null : (int) $stockEntry->quantity,
        ];

        if ($stockEntry->sku === '') {
            $this->entityStore->insert($this->entityType, $values);

            return;
        }

        $values['sku'] = $stockEntry->sku;
        $this->saveOver($values, $stockEntry);
    }

    /**
     * @param list<StockEntry> $samples Items with SKUs, each added unless an item already has its SKU.
     *
     * @throws QueryException           When a write fails.
     * @throws InvalidArgumentException When an entry has no SKU, or a constructed value is invalid.
     *
     * @return array{int, int} How many sold-out items with a SKU were removed, then how many samples were added.
     */
    public function sample(array $samples): array
    {
        $removed = $this->entityStore->query($this->entityType)
            ->where('quantity', 0)
            ->where('sku', null, Comparison::NotEquals)
            ->changes()
            ->delete();
        $added = 0;

        foreach ($samples as $sample) {
            $key = $this->entityStore->insertIfAbsent($this->entityType, [
                'added_on' => $sample->addedOn,
                'certified' => $sample->certified,
                'name' => $sample->name,
                'price' => $sample->price,
                'quantity' => (int) $sample->quantity,
                'sku' => $sample->sku,
            ], 'sku');
            $added += $key === null
                ? 0
                : 1;
        }

        return [$removed, $added];
    }

    /**
     * @param array<string, string|int|bool|null> $values     The item's values, its SKU among them.
     * @param StockEntry                          $stockEntry The posted item.
     *
     * @throws Throwable When the item cannot be stored; the transaction is rolled back.
     */
    private function saveOver(array $values, StockEntry $stockEntry): void
    {
        $entityStore = $this->entityStore;
        $entityType = $this->entityType;
        $entityStore->transaction(static function () use ($entityStore, $entityType, $values, $stockEntry): void {
            $held = $entityStore->query($entityType)->where('sku', $stockEntry->sku)->forUpdate()->first();
            $values['added_on'] = $held instanceof Row
                ? $held->string('added_on')
                : $stockEntry->addedOn;
            $entityStore->upsert($entityType, $values, 'sku');
        });
    }

    /**
     * @param StockQuery $stockQuery What the list asks for.
     *
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return EntityQuery The items its search and lowest price match.
     */
    private function matching(StockQuery $stockQuery): EntityQuery
    {
        $entityQuery = $this->entityStore->query($this->entityType)->search(['name'], $stockQuery->search);

        if ($stockQuery->minPrice !== '') {
            $entityQuery->where('price', $stockQuery->minPrice, Comparison::GreaterOrEqual);
        }

        if ($stockQuery->maxPrice !== '') {
            $entityQuery->where('price', $stockQuery->maxPrice, Comparison::LessOrEqual);
        }

        if ($stockQuery->skus !== []) {
            $entityQuery->whereIn('sku', $stockQuery->skus);
        }

        return $stockQuery->uncounted
            ? $entityQuery->whereAny([new Condition('quantity', null), new Condition('quantity', 0)])
            : $entityQuery;
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
        return new StockItem(
            $row->string('name'),
            $row->string('price'),
            $row->string('added_on'),
            $row->bool('certified'),
            $row->intOrNull('quantity'),
        );
    }
}
