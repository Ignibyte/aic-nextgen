<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\Component\Ticks;
use App\Site\Service\HtmlLayout;
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
        return $this->htmlLayout->content([new Ticks('/events?count=5')], 200);
    }
}
