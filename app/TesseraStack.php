<?php

declare(strict_types=1);

namespace App;

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
 * Tessera's middleware for the app's stack (#70 to #72): the protocol check
 * and the redirect conversion at the edge, and the write ids inside the
 * CSRF guard.
 */
final readonly class TesseraStack
{
    /**
     * @param ResponseBuilder $responseBuilder Makes their answers.
     * @param Database        $database        Holds the write ids.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private Database $database,
    ) {
    }

    /**
     * @param RequestHandlerInterface $requestHandler The whole stack inside.
     *
     * @return RequestHandlerInterface It behind the protocol check, then the redirect conversion.
     */
    public function edge(RequestHandlerInterface $requestHandler): RequestHandlerInterface
    {
        $middlewareStep = new MiddlewareStep(new TesseraRedirectMiddleware($this->responseBuilder), $requestHandler);

        return new MiddlewareStep(new TesseraProtocolMiddleware($this->responseBuilder), $middlewareStep);
    }

    /**
     * @param RequestHandlerInterface $requestHandler The router, or what sits inside the guard.
     *
     * @return RequestHandlerInterface It behind the write ids.
     */
    public function writes(RequestHandlerInterface $requestHandler): RequestHandlerInterface
    {
        return new MiddlewareStep(
            new TesseraWriteMiddleware(new TesseraWrites($this->database), new RequestSession(), $this->responseBuilder),
            $requestHandler,
        );
    }
}
