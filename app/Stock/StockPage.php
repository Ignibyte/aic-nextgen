<?php

declare(strict_types=1);

namespace App\Stock;

use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
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
 * layer as a date, a numeric and a boolean column. The list is the component
 * `content/stock-list`, which a Tessera link refreshes alone (#70).
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

        $refresh = (new Link('Refresh the list', '/stock', false, 'content/stock-list'))->render()->markup();

        return $this->htmlLayout->page(new Regions([new Region('content', [
            new Html('<h2>Stock</h2><p id="stock-latest">' . htmlspecialchars($summary) . '</p>'),
            new KeyedComponent('stock-list', new Html('<ul id="stock">' . $lines . '</ul>')),
            new Html('<p>' . $refresh . '</p>'
                . $this->stockForm->render($serverRequest, new StockEntry('', '', '', false), '')->markup()),
        ])]), 200);
    }
}
