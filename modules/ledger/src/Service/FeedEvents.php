<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Broadcast\Contracts\Broadcast;
use AIC\Broadcast\Contracts\BroadcastEvents;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Tessera\Domain\TesseraEvents;
use App\Theme\Components\FeedEntry\FeedEntry;
use InvalidArgumentException;
use Override;

/**
 * The ledger feed's events (#314): each post on the `ledger.feed` channel is
 * a FeedEntry appended to the `feed` region, under the post's id.
 */
final readonly class FeedEvents implements BroadcastEvents
{
    /**
     * The feed's channel.
     */
    public const string CHANNEL = 'ledger.feed';

    /**
     * @param TesseraEvents $tesseraEvents Makes the element patch.
     */
    public function __construct(
        private TesseraEvents $tesseraEvents = new TesseraEvents(),
    ) {
    }

    /**
     * @param Broadcast $broadcast A post.
     *
     * @throws InvalidArgumentException When the patch cannot be made.
     *
     * @return ServerEvent The post as an entry appended to the feed.
     */
    #[Override]
    public function event(Broadcast $broadcast): ServerEvent
    {
        return $this->tesseraEvents->patchElements('feed', new FeedEntry($broadcast->data), 'append', (string) $broadcast->id, false);
    }
}
