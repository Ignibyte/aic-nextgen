<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Exception\InvalidEnvelope;
use App\Generated\ThemeLayoutName;
use App\Note\Form\StockEntry;
use App\Note\Form\StockForm;
use App\Note\Repository\StockRepository;
use App\Note\Service\StockList;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /stock (#65): every stock item, the latest in full, and the form that
 * adds one. Its date, price and certified flag come back through the entity
 * layer as a date, a numeric and a boolean column. The list is the component
 * `content/stock-list`, which a Tessera link refreshes alone (#70).
 */
final readonly class StockPage implements Page
{
    /**
     * @param PageDocuments   $pageDocuments   Makes the page.
     * @param StockRepository $stockRepository Holds the items.
     * @param StockForm       $stockForm       The form.
     * @param StockList       $stockList       The list, the component Tessera refreshes alone.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
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
        $items = $this->stockRepository->latest(StockList::MOST);

        try {
            $list = $this->stockList->component($serverRequest, $items);
        } catch (InvalidEnvelope) {
            return $this->pageDocuments->content(
                [new Paragraph('The list could not be refreshed. Reload the page.', '')],
                400,
                ThemeLayoutName::Focus,
            );
        }

        return $this->pageDocuments->content([
            new Heading('Stock'),
            new Paragraph($this->stockList->summary($items), 'stock-latest'),
            $list,
            $this->stockList->links(),
            $this->stockForm->form($serverRequest, new StockEntry('', '', '', false), ''),
        ], 200, ThemeLayoutName::Focus, 'Stock');
    }
}
