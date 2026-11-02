<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * What the profiler pages show beyond the headers they list (#230): the
 * route's kind, the browser the request came from, the names (never the
 * values) of its headers and posted fields, and a Tessera answer's markup.
 */
final readonly class RequestDetail
{
    /**
     * @param string       $kind    The route's kind (`Page`, `Api` or `Stream`); empty when no route matched.
     * @param string       $browser A key for the browser's session, a hash of its id; empty without a session.
     * @param list<string> $headers The request's header names.
     * @param list<string> $fields  The posted form fields' names.
     * @param string       $body    A Tessera fragment answer's markup, at most 64 KiB; empty for anything else.
     * @param string       $query   The query string, as the profiler's pages read it back; the writer keeps it apart.
     */
    public function __construct(
        public string $kind = '',
        public string $browser = '',
        public array $headers = [],
        public array $fields = [],
        public string $body = '',
        public string $query = '',
    ) {
    }
}
