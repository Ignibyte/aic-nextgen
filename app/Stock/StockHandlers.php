<?php

declare(strict_types=1);

namespace App\Stock;

use AIC\Controller\Application\PageHandlers;
use AIC\Database\Contracts\Database;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Application\PostedFields;
use AIC\Tessera\Application\TesseraProps;
use App\Site\HtmlLayout;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The stock demo's controllers (#65), each wrapped in the Page handler, by the
 * handler id config/routes.php names.
 */
final readonly class StockHandlers
{
    /**
     * @param HtmlLayout    $htmlLayout    Makes the pages.
     * @param PageHandlers  $pageHandlers  Wraps each page with the response writer and the blocks.
     * @param Database      $database      Holds the items.
     * @param EnvelopeCodec $envelopeCodec Seals and opens the list's props (#74).
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PageHandlers $pageHandlers,
        private Database $database,
        private EnvelopeCodec $envelopeCodec,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $stockRepository = new StockRepository(new EntityStore($this->database));
        $stockForm = new StockForm();
        $stockList = new StockList($this->envelopeCodec, new TesseraProps($this->envelopeCodec));

        return [
            StockPage::class => $this->pageHandlers->handler(new StockPage($this->htmlLayout, $stockRepository, $stockForm, $stockList)),
            StockPost::class => $this->pageHandlers->handler(
                new StockPost($this->htmlLayout, $stockRepository, $stockForm, new PostedFields()),
            ),
        ];
    }
}
