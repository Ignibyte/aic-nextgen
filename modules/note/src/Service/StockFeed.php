<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Controller\Domain\XmlAttribute;
use AIC\Controller\Domain\XmlDocument;
use AIC\Controller\Domain\XmlElement;
use AIC\Database\Contracts\QueryException;
use App\Note\Entity\StockItem;
use App\Note\Form\StockQuery;
use App\Note\Repository\StockRepository;
use InvalidArgumentException;

use function array_map;
use function count;

/**
 * The stock list as an XML feed (#233), the `/stock.xml` answer: a `stock`
 * element counting the matching items, each an `item` with its added day,
 * certification and quantity as attributes and its name and price as
 * elements, oldest first, as the CSV export orders them.
 */
final readonly class StockFeed
{
    /**
     * @param StockRepository $stockRepository The stock.
     */
    public function __construct(
        private StockRepository $stockRepository,
    ) {
    }

    /**
     * @param StockQuery $stockQuery What the list asks for; its page does not apply.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return XmlDocument The feed.
     */
    public function document(StockQuery $stockQuery): XmlDocument
    {
        $items = array_map(self::item(...), $this->stockRepository->all($stockQuery));

        return new XmlDocument(new XmlElement('stock', [new XmlAttribute('count', (string) count($items))], '', $items));
    }

    /**
     * @param StockItem $stockItem An item.
     *
     * @throws InvalidArgumentException When a value holds a character XML 1.0 forbids.
     *
     * @return XmlElement Its `item` element.
     */
    private static function item(StockItem $stockItem): XmlElement
    {
        $attributes = [
            new XmlAttribute('addedOn', $stockItem->addedOn),
            new XmlAttribute('certified', $stockItem->certified ? 'yes' : 'no'),
            new XmlAttribute('quantity', $stockItem->quantity === null ? '' : (string) $stockItem->quantity),
        ];

        return new XmlElement('item', $attributes, '', [
            new XmlElement('name', [], $stockItem->name),
            new XmlElement('price', [], $stockItem->price),
        ]);
    }
}
