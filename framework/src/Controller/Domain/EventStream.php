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
     * @param EventSource $eventSource       Gives the events, one at a time.
     * @param bool        $pausedWhileHidden Whether Tessera closes it while the page is hidden (#259).
     */
    public function __construct(
        private EventSource $eventSource,
        private bool $pausedWhileHidden = false,
    ) {
    }

    /**
     * @return self The same stream, answered with `Tessera-Stream-Pause: hidden` (#259): Tessera closes its
     *              connection while the page is hidden and reconnects with `Last-Event-ID` when it is visible.
     *              For a stream whose events can wait; one whose events must arrive while hidden stays as it is.
     */
    public function pausedWhileHidden(): self
    {
        return new self($this->eventSource, true);
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The response, its body pulling the events as it is read.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->events($this->eventSource, $this->pausedWhileHidden);
    }
}
