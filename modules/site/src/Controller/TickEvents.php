<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\EventStream;
use App\Site\Service\TickSource;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function ctype_digit;
use function is_string;
use function max;
use function min;

/**
 * GET /events: Server-Sent Events, `count` ticks (from the query, 1 to 10,
 * 3 by default), each sent as it comes as a Tessera fragment for the `ticks`
 * region; a reconnect with `Last-Event-ID` resumes after that tick (#73).
 */
final readonly class TickEvents implements Stream
{
    /**
     * @param ServerRequestInterface $serverRequest The request, with optional `count` and `resume` (`1`: end the
     *                                              connection without closing the stream, #202) query fields and
     *                                              `Last-Event-ID`.
     *
     * @return StreamResponse The event stream.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        $query = $serverRequest->getQueryParams();
        $count = array_key_exists('count', $query) && is_string($query['count'])
            ? $query['count']
            : '3';
        $ticks = ctype_digit($count)
            ? (int) $count
            : 3;
        $seen = $serverRequest->getHeaderLine('Last-Event-ID');
        $after = ctype_digit($seen)
            ? (int) $seen
            : 0;

        return new EventStream(new TickSource(max(1, min(10, $ticks)), $after, ($query['resume'] ?? '') === '1'));
    }
}
