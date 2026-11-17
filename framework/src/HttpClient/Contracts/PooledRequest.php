<?php

declare(strict_types=1);

namespace AIC\HttpClient\Contracts;

use Psr\Http\Message\RequestInterface;

/**
 * One request of a pool (#305), with its own time limit.
 */
final readonly class PooledRequest
{
    /**
     * @param RequestInterface $request   The request.
     * @param int              $timeoutMs Milliseconds it may take in all; 0 for the client's default.
     */
    public function __construct(
        public RequestInterface $request,
        public int $timeoutMs = 0,
    ) {
    }
}
