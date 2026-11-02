<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * One recorded request as the profiler's list shows it (#230).
 */
final readonly class ProfileRow
{
    /**
     * @param string $id           The profile's id, its `X-AIC-Profile`.
     * @param string $kept         When it was kept, `HH:MM:SS` in the database's time zone.
     * @param string $method       The request's method.
     * @param string $path         Its path.
     * @param int    $status       The answer's status.
     * @param int    $microseconds Its total time.
     * @param string $kind         The route's kind; empty when no route matched.
     * @param string $tessera      The Tessera request's kind (`visit`, `submit`, …); empty for a plain request.
     * @param string $targets      Its `Tessera-Targets`; empty for none.
     * @param int    $statements   How many statements it ran.
     * @param int    $bytes        The answer body's length.
     */
    public function __construct(
        public string $id,
        public string $kept,
        public string $method,
        public string $path,
        public int $status,
        public int $microseconds,
        public string $kind,
        public string $tessera,
        public string $targets,
        public int $statements,
        public int $bytes,
    ) {
    }
}
