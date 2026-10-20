<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * What a site answered to one request: the parts gate:23 compares.
 */
final readonly class HttpExchange
{
    /**
     * @param int    $status      The status; 0 when the request got no answer.
     * @param string $contentType The `Content-Type`, as sent; empty for none.
     * @param string $location    The `Location`, as sent; empty for none.
     * @param string $body        The body.
     */
    public function __construct(
        public int $status,
        public string $contentType,
        public string $location,
        public string $body,
    ) {
    }
}
