<?php

declare(strict_types=1);

namespace AIC\Sapi\Contracts;

/**
 * One request as the SAPI gave it: the server params, narrowed to scalars by the
 * worker, and the raw body; for a multipart post, the form fields and uploads
 * the SAPI parsed (#44), since PHP-FPM does not give the body for one.
 *
 * The query, cookies and urlencoded form fields are parsed from these strings,
 * so no superglobal array reaches framework code.
 */
final readonly class SapiRequest
{
    /**
     * @param array<string, scalar> $server  The server params (`REQUEST_METHOD`, `HTTP_*`, ...).
     * @param string                $body    The raw request body.
     * @param array<string, string> $fields  A multipart post's form fields; empty otherwise.
     * @param list<SapiUpload>      $uploads A multipart post's uploads, one per field; empty otherwise.
     */
    public function __construct(
        public array $server,
        public string $body,
        public array $fields = [],
        public array $uploads = [],
    ) {
    }
}
