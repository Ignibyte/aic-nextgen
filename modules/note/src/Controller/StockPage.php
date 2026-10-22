<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Exception\InvalidEnvelope;
use App\Note\Entity\StockItem;
use App\Note\Form\StockEntry;
use App\Note\Form\StockForm;
use App\Note\Repository\StockRepository;
use App\Note\Service\StockList;
use App\Site\Service\HtmlLayout;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function count;

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
     * @throws LogicException When the required application state is unavailable.
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
            return $this->htmlLayout->content([new Paragraph('The list could not be refreshed. Reload the page.', '')], 400);
        }

        $latest = $items === []
            ? null
            : $items[count($items) - 1];
        $summary = $latest instanceof StockItem
            ? 'Latest: ' . $latest->line() . '.'
            : 'Nothing in stock yet.';
        return $this->htmlLayout->content([
            new Heading('Stock'),
            new Paragraph($summary, 'stock-latest'),
            $list,
            $this->stockList->links(),
            $this->stockForm->form($serverRequest, new StockEntry('', '', '', false), ''),
        ], 200);
    }
}
