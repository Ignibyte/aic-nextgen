<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * What a profiled request was and what it answered (#229), by name: only the
 * listed Tessera headers, never a cookie, an authorization or a session token.
 */
final readonly class RequestFacts
{
    /**
     * @param string                $method  The request's method.
     * @param string                $path    Its path.
     * @param string                $query   Its query string, without the `?`.
     * @param int                   $status  The answer's status.
     * @param string                $handler The matched route's handler class; empty when no route matched.
     * @param string                $user    The signed-in user's id; empty for an anonymous visitor.
     * @param array<string, string> $tessera The Tessera request headers it carried, by name.
     * @param array<string, string> $answer  The answer's content type and Tessera headers, by name.
     * @param int                   $bytes   The answer body's length; 0 for a stream, which has none yet.
     * @param RequestDetail         $detail  The kind, the browser, the names and a Tessera answer's markup (#230).
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $query,
        public int $status,
        public string $handler,
        public string $user,
        public array $tessera,
        public array $answer,
        public int $bytes,
        public RequestDetail $detail = new RequestDetail(),
    ) {
    }
}
