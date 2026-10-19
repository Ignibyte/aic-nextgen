<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The Api kind: an entry point whose answer is an API answer: JSON.
 *
 * Every entry point is exactly one kind, and each kind returns only its own
 * sealed response type; the phpat guards in gates/architecture hold both.
 */
interface Api
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return ApiResponse The answer.
     */
    public function api(ServerRequestInterface $serverRequest): ApiResponse;
}
