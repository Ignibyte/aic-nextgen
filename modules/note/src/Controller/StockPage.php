<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\SignalPatch;
use AIC\Controller\Domain\PageUnchanged;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Exception\InvalidEnvelope;
use App\Generated\ThemeLayoutName;
use App\Note\Form\StockForm;
use App\Note\Form\StockQueries;
use App\Note\Service\StockList;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /stock (#65): every stock item, the latest in full, and the form that
 * adds one. Its date, price and certified flag come back through the entity
 * layer as a date, a numeric and a boolean column. The list is the component
 * `content/stock-list`, which a Tessera link refreshes alone (#70). It reads
 * nothing itself: the list and the totals read their items when they are
 * rendered, so a refresh of the list alone does not add up the totals (#192).
 */
final readonly class StockPage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     * @param StockForm     $stockForm     The form.
     * @param StockList     $stockList     The list, the component Tessera refreshes alone.
     * @param StockQueries  $stockQueries  Reads what the list asks for.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private StockForm $stockForm,
        private StockList $stockList,
        private StockQueries $stockQueries,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session, an optional `limit`, `q`,
     *                                              `min_price` and `page`, and maybe the list's props.
     *
     * @throws QueryException When the list's items cannot be counted.
     * @throws JsonException  When the list's props cannot be sealed.
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The page, its fragment answer setting the list's `stockRefreshed` (#260); 204 for a
     *                      poll of the list when nothing changed (#193); 400 when the list's props were altered.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $stockQuery = $this->stockQueries->query($serverRequest);
        $version = $this->stockList->version($stockQuery);

        try {
            if ($this->stockList->unchanged($serverRequest, $version)) {
                return new PageUnchanged();
            }

            $parts = $this->stockList->parts($serverRequest, $stockQuery, $version);
        } catch (InvalidEnvelope) {
            return $this->refused();
        }

        $components = [
            new Heading('Stock'),
            ...$parts,
            $this->stockForm->blank($serverRequest),
        ];

        return $this->pageDocuments->content($components, 200, 'Stock', ThemeLayoutName::Focus)
            ->prefetchable()
            ->withSignalPatch(new SignalPatch(StockList::TARGET, '{"stockRefreshed":true}'));
    }

    /**
     * @throws LogicException When a constructed value is invalid.
     *
     * @return PageResponse The 400 page for a list whose props were altered.
     */
    private function refused(): PageResponse
    {
        return $this->pageDocuments->content(
            [new Paragraph('The list could not be refreshed. Reload the page.', '')],
            400,
            'Stock',
            ThemeLayoutName::Focus,
        );
    }
}
