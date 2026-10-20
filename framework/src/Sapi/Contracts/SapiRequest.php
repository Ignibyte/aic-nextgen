<?php

declare(strict_types=1);

namespace AIC\Sapi\Contracts;

/**
 * One request as the SAPI gave it: the server params, narrowed to scalars by the
 * worker, and the raw body.
 *
 * The query, cookies and form fields are parsed from these strings, so no
 * superglobal array reaches framework code.
 */
final readonly class SapiRequest
{
    /**
     * @param array<string, scalar> $server The server params (`REQUEST_METHOD`, `HTTP_*`, ...).
     * @param string                $body   The raw request body.
     */
    public function __construct(
        public array $server,
        public string $body,
    ) {
    }
}
