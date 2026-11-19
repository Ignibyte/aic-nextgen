<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Broadcast\Application\Broadcasts;
use AIC\Broadcast\Domain\Channel;
use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Database\Contracts\QueryException;
use App\Ledger\Service\FeedEvents;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /ledger/feed/events (#314): the `ledger.feed` channel as Server-Sent
 * Events, each post a Tessera element patch, resuming after
 * `Last-Event-ID`. Its route's need (`ledger.feed`) is who may read it.
 */
final readonly class LedgerFeedStream implements Stream
{
    /**
     * @param Broadcasts $broadcasts Makes the channel's stream.
     * @param FeedEvents $feedEvents Makes each post's event.
     */
    public function __construct(
        private Broadcasts $broadcasts,
        private FeedEvents $feedEvents = new FeedEvents(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, perhaps with `Last-Event-ID`.
     *
     * @throws InvalidArgumentException When the channel is not one.
     * @throws QueryException           When a slot cannot be asked for.
     *
     * @return StreamResponse The stream.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->broadcasts->stream(new Channel(FeedEvents::CHANNEL), $this->feedEvents, $serverRequest);
    }
}
