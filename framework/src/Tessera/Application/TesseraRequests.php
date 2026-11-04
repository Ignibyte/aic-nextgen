<?php

declare(strict_types=1);

namespace AIC\Tessera\Application;

use AIC\Tessera\Domain\TesseraRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A request's Tessera headers, read in one place (#268): `Tessera-Request`, `Tessera-Protocol`,
 * `Tessera-Targets` and `Tessera-Layout` as a `TesseraRequest`. The framework's page handler,
 * protocol middleware, props and profiler read them through it, and so does an app that asks what
 * a request refreshes (`refreshesOnly('@jobs-live')`). A class takes it as a defaulted constructor
 * argument, `private TesseraRequests $tesseraRequests = new TesseraRequests()`.
 *
 * @api
 */
final readonly class TesseraRequests
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return TesseraRequest Its Tessera headers; a plain request's are empty.
     */
    public function read(ServerRequestInterface $serverRequest): TesseraRequest
    {
        return new TesseraRequest(
            $serverRequest->getHeaderLine(TesseraRequest::REQUEST),
            $serverRequest->getHeaderLine(TesseraRequest::PROTOCOL),
            $serverRequest->getHeaderLine(TesseraRequest::TARGETS),
            $serverRequest->getHeaderLine(TesseraRequest::LAYOUT),
        );
    }
}
