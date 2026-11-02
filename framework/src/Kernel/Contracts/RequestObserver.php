<?php

declare(strict_types=1);

namespace AIC\Kernel\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Told when a dynamic request starts and what it answered (#229): the seam a
 * profiler records a request by. It sits inside the public boundary, so an
 * asset or a stored file never reaches it. The default observer does nothing.
 */
interface RequestObserver
{
    /**
     * @param ServerRequestInterface $serverRequest The request, before the framework's middleware.
     */
    public function started(ServerRequestInterface $serverRequest): void;

    /**
     * @param ServerRequestInterface $serverRequest The request, as it started.
     * @param ResponseInterface      $response      What the app answered.
     *
     * @return ResponseInterface The response to send: the same one, or it with a header added.
     */
    public function finished(ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface;
}
