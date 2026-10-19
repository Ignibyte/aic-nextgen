<?php

declare(strict_types=1);

namespace AIC\Middleware\Application;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * One step of a PSR-15 middleware stack: a middleware, and the handler it hands
 * the request on to, which is the next step or the final handler.
 *
 * A stack is built from its innermost step outwards; it holds no list, so
 * elephc never reads a middleware out of an array.
 */
final readonly class MiddlewareStep implements RequestHandlerInterface
{
    /**
     * @param MiddlewareInterface     $middleware     The middleware this step runs.
     * @param RequestHandlerInterface $requestHandler The handler after it.
     */
    public function __construct(
        private MiddlewareInterface $middleware,
        private RequestHandlerInterface $requestHandler,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The request.
     *
     * @return ResponseInterface The middleware's response.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->requestHandler);
    }
}
