<?php

declare(strict_types=1);

namespace App\Stock;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Site\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function count;
use function htmlspecialchars;

/**
 * GET /stock (#65): every stock item, the latest in full, and the form that
 * adds one. Its date, price and certified flag come back through the entity
 * layer as a date, a numeric and a boolean column.
 */
final readonly class StockPage implements Page
{
    /**
     * @param HtmlLayout      $htmlLayout      Makes the page.
     * @param StockRepository $stockRepository Holds the items.
     * @param StockForm       $stockForm       The form.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private StockRepository $stockRepository,
        private StockForm $stockForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws QueryException When the items cannot be read.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $items = $this->stockRepository->all();
        $lines = '';

        foreach ($items as $item) {
            $lines .= '<li>' . htmlspecialchars($item->line()) . '</li>';
        }

        $latest = $items[count($items) - 1] ?? null;
        $summary = $latest instanceof StockItem
            ? 'Latest: ' . $latest->line() . '.'
            : 'Nothing in stock yet.';

        return $this->htmlLayout->content(
            '<h2>Stock</h2><p id="stock-latest">' . htmlspecialchars($summary) . '</p><ul id="stock">' . $lines . '</ul>'
                . $this->stockForm->render($serverRequest, new StockEntry('', '', '', false), '')->markup(),
            200,
        );
    }
}
