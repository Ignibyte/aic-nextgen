<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ResponseWriter;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * An Api's answer as Server-Sent Events (#333): MCP's Streamable HTTP answers
 * a POST with JSON or with an event stream, so one Api route answers both. The
 * events are pulled from the source as the body is read, and the last ends it.
 *
 * @api
 */
final readonly class ApiEvents implements ApiResponse
{
    /**
     * @param EventSource $eventSource Gives the events.
     */
    public function __construct(
        private EventSource $eventSource,
    ) {
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The `text/event-stream` response.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->events($this->eventSource);
    }
}
