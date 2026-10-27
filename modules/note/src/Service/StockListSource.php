<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Component\Contracts\ComponentSource;
use AIC\Database\Contracts\QueryException;
use App\Note\Entity\StockItem;
use App\Note\Form\StockQuery;
use App\Note\Repository\StockRepository;
use App\Theme\Components\StockListing\StockListing;
use InvalidArgumentException;
use Override;

use function array_slice;
use function count;

/**
 * The stock list's items, read when the list is rendered (#192): a refresh
 * of the list alone reads them, and a refresh of another part of the page
 * does not.
 */
final readonly class StockListSource implements ComponentSource
{
    /**
     * @param StockRepository $stockRepository Holds the items.
     * @param StockQuery      $stockQuery      What the list asks for.
     * @param int             $limit           How many it shows, 1 to `StockList::MOST`.
     */
    public function __construct(
        private StockRepository $stockRepository,
        private StockQuery $stockQuery,
        private int $limit,
    ) {
    }

    /**
     * @throws QueryException           When the items cannot be read.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return StockListing The last items, oldest first, and the latest item's line.
     */
    #[Override]
    public function build(): StockListing
    {
        $items = $this->stockRepository->latest(StockList::MOST, $this->stockQuery);
        $lines = [];

        foreach (array_slice($items, -$this->limit) as $stockItem) {
            $lines[] = $stockItem->line();
        }

        return new StockListing($this->limit, $lines, self::latest($items));
    }

    /**
     * @param list<StockItem> $items The newest items, oldest first.
     *
     * @return string The latest item's line, or that there is none.
     */
    private static function latest(array $items): string
    {
        $latest = $items === []
            ? null
            : $items[count($items) - 1];

        return $latest instanceof StockItem
            ? 'Latest: ' . $latest->line() . '.'
            : 'Nothing in stock yet.';
    }
}
