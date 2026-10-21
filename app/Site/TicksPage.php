<?php

declare(strict_types=1);

namespace App\Site;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /ticks (#73): a list Tessera fills from the `/events` stream, five
 * ticks appended one by one, and a count bound to the `ticks` signal the
 * stream patches; then the stream closes.
 */
final readonly class TicksPage implements Page
{
    /**
     * @param HtmlLayout $htmlLayout Makes the page.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->htmlLayout->content(
            '<h2>Ticks</h2><p>Five ticks, streamed from <code>/events</code> by Tessera into the list below.</p>'
                . '<p data-signals__document=\'{"ticks":0}\'>Ticks seen: <strong id="ticks-seen" data-text="$ticks">0</strong></p>'
                . '<ol data-region="ticks" data-stream="/events?count=5"></ol>',
            200,
        );
    }
}
