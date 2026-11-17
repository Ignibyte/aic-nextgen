<?php

declare(strict_types=1);

namespace AIC\HttpClient\Contracts;

/**
 * One call the app made to an upstream (#305), as an observer is told it.
 */
final readonly class OutboundCall
{
    /**
     * @param string $method       The request method.
     * @param string $url          The request URI.
     * @param int    $status       The response's status; 0 when it got none.
     * @param int    $microseconds How long it took.
     */
    public function __construct(
        public string $method,
        public string $url,
        public int $status,
        public int $microseconds,
    ) {
    }
}
