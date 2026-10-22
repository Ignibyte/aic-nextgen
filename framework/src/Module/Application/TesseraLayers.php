<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use AIC\Middleware\Application\MiddlewareStep;
use AIC\Session\Application\RequestSession;
use AIC\Tessera\Application\TesseraProtocolMiddleware;
use AIC\Tessera\Application\TesseraRedirectMiddleware;
use AIC\Tessera\Application\TesseraWriteMiddleware;
use AIC\Tessera\Application\TesseraWrites;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tessera's three middleware in the framework's stack (#70 to #72, placed by
 * #93): the protocol check and the redirect rewrite at the edge, the write
 * ledger innermost, just before the router.
 */
final readonly class TesseraLayers
{
    /**
     * @param ResponseBuilder $responseBuilder Makes the protocol and conflict responses.
     * @param Database        $database        Holds the write ledger.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private Database $database,
    ) {
    }

    /**
     * @param RequestHandlerInterface $requestHandler The stack inside the edge.
     *
     * @return RequestHandlerInterface It behind the protocol check and the redirect rewrite.
     */
    public function edge(RequestHandlerInterface $requestHandler): RequestHandlerInterface
    {
        $middlewareStep = new MiddlewareStep(new TesseraRedirectMiddleware($this->responseBuilder), $requestHandler);

        return new MiddlewareStep(new TesseraProtocolMiddleware($this->responseBuilder), $middlewareStep);
    }

    /**
     * @param RequestHandlerInterface $requestHandler The router.
     *
     * @return RequestHandlerInterface It behind the write ledger.
     */
    public function writes(RequestHandlerInterface $requestHandler): RequestHandlerInterface
    {
        return new MiddlewareStep(
            new TesseraWriteMiddleware(new TesseraWrites($this->database), new RequestSession(), $this->responseBuilder),
            $requestHandler,
        );
    }
}
