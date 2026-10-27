<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\Paragraph;
use AIC\Database\Contracts\QueryException;
use App\Note\Form\StockQuery;
use App\Note\Repository\StockRepository;
use InvalidArgumentException;
use Override;

/**
 * The totals of what a filtered stock list matches, added up when they are
 * rendered (#192), so a refresh of the list alone does not add them up.
 */
final readonly class StockTotalsSource implements ComponentSource
{
    /**
     * @param StockRepository $stockRepository Adds up the items.
     * @param StockQuery      $stockQuery      What the list asks for; its page does not apply.
     */
    public function __construct(
        private StockRepository $stockRepository,
        private StockQuery $stockQuery,
    ) {
    }

    /**
     * @throws QueryException           When the totals cannot be read.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return Paragraph How many items match, what they are worth, the dearest price and their SKUs.
     */
    #[Override]
    public function build(): Paragraph
    {
        return new Paragraph($this->stockRepository->totals($this->stockQuery)->line(), 'stock-totals');
    }
}
