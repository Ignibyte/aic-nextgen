<?php

declare(strict_types=1);

namespace App\Stock\Controller;

use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Exception\InvalidEnvelope;
use App\Site\Service\HtmlLayout;
use App\Stock\Component\StockList;
use App\Stock\Entity\StockItem;
use App\Stock\Form\StockEntry;
use App\Stock\Form\StockForm;
use App\Stock\Repository\StockRepository;
use JsonException;
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
     * @param StockList       $stockList       The list, the component Tessera refreshes alone.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private StockRepository $stockRepository,
        private StockForm $stockForm,
        private StockList $stockList,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session, an optional `limit` and maybe
     *                                              the list's props.
     *
     * @throws QueryException When the items cannot be read.
     * @throws JsonException  When the list's props cannot be sealed.
     *
     * @return PageResponse The page; 400 when the list's props were altered.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $items = $this->stockRepository->all();

        try {
            $list = $this->stockList->component($serverRequest, $items);
        } catch (InvalidEnvelope) {
            return $this->htmlLayout->content('<p>The list could not be refreshed. Reload the page.</p>', 400);
        }

        $latest = $items[count($items) - 1] ?? null;
        $summary = $latest instanceof StockItem
            ? 'Latest: ' . $latest->line() . '.'
            : 'Nothing in stock yet.';
        $html = $this->stockForm->render($serverRequest, new StockEntry('', '', '', false), '');

        return $this->htmlLayout->page(new Regions([new Region('content', [
            new Html('<h2>Stock</h2><p id="stock-latest">' . htmlspecialchars($summary) . '</p>'),
            $list,
            $this->stockList->links()->append($html),
        ])]), 200);
    }
}
