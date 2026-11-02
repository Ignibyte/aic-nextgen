<?php

declare(strict_types=1);

namespace AIC\Routing\Contracts;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Told when the router hands a request to its handler and when the handler
 * answers (#229): the seam a profiler times the middleware and the handler by.
 * The router's default observer does nothing.
 */
interface RouteObserver
{
    /**
     * @param ServerRequestInterface  $serverRequest  The request as the handler gets it, its session and path
     *                                                parameters on it.
     * @param string                  $handler        The matched route's handler: the controller's class.
     * @param RequestHandlerInterface $requestHandler What answers it: the kind's handler around the controller.
     */
    public function routed(
        ServerRequestInterface $serverRequest,
        string $handler,
        RequestHandlerInterface $requestHandler,
    ): void;

    /**
     * The handler answered.
     */
    public function answered(): void;
}
