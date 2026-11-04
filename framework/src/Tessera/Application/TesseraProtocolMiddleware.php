<?php

declare(strict_types=1);

namespace AIC\Tessera\Application;

use AIC\Http\Application\ResponseBuilder;
use AIC\Tessera\Domain\TesseraRequest;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Refuses a Tessera request in a protocol AIC does not speak (#70, protocol
 * section 1): `400` with `Tessera-Protocol` naming the version it does, before
 * any middleware that could act on the request. It sits outermost.
 */
final readonly class TesseraProtocolMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseBuilder $responseBuilder Makes the refusal.
     * @param TesseraRequests $tesseraRequests Reads the request's Tessera headers (#268).
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private TesseraRequests $tesseraRequests = new TesseraRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The refusal, or the rest of the stack's answer.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $tesseraRequest = $this->tesseraRequests->read($request);

        if (!$tesseraRequest->isTessera() || $tesseraRequest->speaksVersion()) {
            return $handler->handle($request);
        }

        $response = $this->responseBuilder->create(400, 'text/plain; charset=utf-8', '');

        return $this->responseBuilder->withHeader(
            $this->responseBuilder->withHeader($response, TesseraRequest::PROTOCOL, TesseraRequest::VERSION),
            'Vary',
            TesseraRequest::VARY,
        );
    }
}
