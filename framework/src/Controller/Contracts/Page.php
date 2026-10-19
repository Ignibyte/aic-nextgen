<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The Page kind: an entry point whose answer is an HTML page: a full document, or a redirect after a write.
 *
 * Every entry point is exactly one kind, and each kind returns only its own
 * sealed response type; the phpat guards in gates/architecture hold both.
 */
interface Page
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return PageResponse The answer.
     */
    public function page(ServerRequestInterface $serverRequest): PageResponse;
}
