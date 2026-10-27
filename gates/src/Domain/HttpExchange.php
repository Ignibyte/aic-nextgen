<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * What a site answered to one request: the parts gate:23 compares, and the
 * cookie it set, which the next requests send back. Of the headers, it keeps
 * the protocol's and caching's (#173): `Tessera-*`, `Vary`, `Cache-Control`.
 */
final readonly class HttpExchange
{
    /**
     * @param int                   $status      The status; 0 when the request got no answer.
     * @param string                $contentType The `Content-Type`, as sent; empty for none.
     * @param string                $location    The `Location`, as sent; empty for none.
     * @param string                $body        The body.
     * @param string                $cookie      The cookie it set, `name=value`; empty for none.
     * @param array<string, string> $headers     The compared headers, by lower-case name.
     */
    public function __construct(
        public int $status,
        public string $contentType,
        public string $location,
        public string $body,
        public string $cookie = '',
        public array $headers = [],
    ) {
    }
}
