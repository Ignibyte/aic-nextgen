<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The Stream kind: an entry point whose answer is a stream: a file download.
 *
 * Every entry point is exactly one kind, and each kind returns only its own
 * sealed response type; the phpat guards in gates/architecture hold both.
 */
interface Stream
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return StreamResponse The answer.
     */
    public function stream(ServerRequestInterface $serverRequest): StreamResponse;
}
