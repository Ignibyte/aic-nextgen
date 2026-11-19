<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Ledger\Form\FeedForm;
use App\Theme\Components\Feed\Feed;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /ledger/feed (#314): the post form, and the feed Tessera fills from
 * `/ledger/feed/events` as anyone posts.
 */
final readonly class LedgerFeedPage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     * @param FeedForm      $feedForm      The post form.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private FeedForm $feedForm = new FeedForm(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the request carries no session, or a constructed value is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->pageDocuments->content(
            [$this->feedForm->form($serverRequest), new Feed('/ledger/feed/events')],
            200,
            'Ledger feed',
        );
    }
}
