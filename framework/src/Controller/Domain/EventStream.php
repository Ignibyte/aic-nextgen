<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Contracts\StreamResponse;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * A stream answered with Server-Sent Events (`text/event-stream`), each sent
 * as its source gives it.
 */
final readonly class EventStream implements StreamResponse
{
    /**
     * @param EventSource $eventSource Gives the events, one at a time.
     */
    public function __construct(
        private EventSource $eventSource,
    ) {
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The response, its body pulling the events as it is read.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->events($this->eventSource);
    }
}
