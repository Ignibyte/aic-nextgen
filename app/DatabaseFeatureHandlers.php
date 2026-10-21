<?php

declare(strict_types=1);

namespace App;

use AIC\Controller\Application\PageHandlers;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use App\Auth\AuthHandlers;
use App\Content\ContentHandlers;
use App\Job\JobHandlers;
use App\Site\HtmlLayout;
use App\Stock\StockHandlers;
use Psr\Http\Server\RequestHandlerInterface;

use function array_merge;

/**
 * The handlers of the features whose pages need only the layout, the page
 * handler and the database: sign-in, content pages, jobs and stock.
 */
final readonly class DatabaseFeatureHandlers
{
    /**
     * @param HtmlLayout    $htmlLayout    Makes the pages.
     * @param PageHandlers  $pageHandlers  Wraps each page with the response writer and the blocks.
     * @param Database      $database      The database.
     * @param EnvelopeCodec $envelopeCodec Seals the stock list's props.
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
        return array_merge(
            (new AuthHandlers($this->htmlLayout, $this->pageHandlers, $this->database))->byId(),
            (new ContentHandlers($this->htmlLayout, $this->pageHandlers, $this->database))->byId(),
            (new JobHandlers($this->htmlLayout, $this->pageHandlers, $this->database))->byId(),
            (new StockHandlers($this->htmlLayout, $this->pageHandlers, $this->database, $this->envelopeCodec))->byId(),
        );
    }
}
