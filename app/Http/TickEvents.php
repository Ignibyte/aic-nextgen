<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\EventStream;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function is_string;
use function max;
use function min;

/**
 * GET /events: Server-Sent Events, `count` ticks (from the query, 1 to 10,
 * 3 by default), each sent as it comes.
 */
final readonly class TickEvents implements Stream
{
    /**
     * @param ServerRequestInterface $serverRequest The request, with an optional `count` query field.
     *
     * @return StreamResponse The event stream.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        $count = $serverRequest->getQueryParams()['count'] ?? '3';
        $ticks = is_string($count) && ctype_digit($count)
            ? (int) $count
            : 3;

        return new EventStream(new TickSource(max(1, min(10, $ticks))));
    }
}
