<?php

declare(strict_types=1);

namespace AIC\Broadcast\Application;

use AIC\Broadcast\Contracts\BroadcastEvents;
use AIC\Broadcast\Domain\Channel;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Domain\EventStream;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function strlen;

/**
 * A channel's stream for a request (#314), what a Stream controller answers:
 * it takes a slot under `BroadcastConfig::$streams` and resumes after the
 * request's `Last-Event-ID`. Who may read the channel is the route's
 * `needs:` (#304).
 */
final readonly class Broadcasts
{
    /**
     * @param Database    $database    The request's database connection.
     * @param AppConfig   $appConfig   The app's settings: the hold, the keep-alive and the cap.
     * @param StreamSlots $streamSlots The site-wide cap.
     */
    public function __construct(
        private Database $database,
        private AppConfig $appConfig,
        private StreamSlots $streamSlots,
    ) {
    }

    /**
     * @param Channel                $channel         The channel.
     * @param BroadcastEvents        $broadcastEvents Makes each event the page reads.
     * @param ServerRequestInterface $serverRequest   The stream's request.
     *
     * @throws QueryException When a slot cannot be asked for.
     *
     * @return EventStream The stream: the channel's events for one hold, or one reconnect when every slot is held.
     */
    public function stream(
        Channel $channel,
        BroadcastEvents $broadcastEvents,
        ServerRequestInterface $serverRequest,
    ): EventStream {
        return new EventStream($this->source($channel, $broadcastEvents, $serverRequest->getHeaderLine('Last-Event-ID')));
    }

    /**
     * The channel's events as a source another one can wrap (#333), as MCP's GET stream does.
     *
     * @param Channel         $channel         The channel.
     * @param BroadcastEvents $broadcastEvents Makes each event.
     * @param string          $seen            The last event id the client saw; empty, or not one, for none.
     *
     * @throws QueryException When a slot cannot be asked for.
     *
     * @return EventSource The channel's events for one hold, or one reconnect when every slot is held.
     */
    public function source(Channel $channel, BroadcastEvents $broadcastEvents, string $seen): EventSource
    {
        $broadcastConfig = $this->appConfig->broadcast;
        $admitted = $this->streamSlots->claim($broadcastConfig->streams);
        $after = ctype_digit($seen) && strlen($seen) < 19
            ? (int) $seen
            : -1;

        return new BroadcastSource($this->database, $channel, $broadcastEvents, $broadcastConfig, $admitted, $after);
    }
}
