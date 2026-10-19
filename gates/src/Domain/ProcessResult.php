<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Captured outcome of a `ProcessRunner::run()` call.
 *
 * Immutable value object holding the child process's exit code together with
 * its full stdout and stderr. Stdout and stderr are kept separate so callers
 * can apply different handling — e.g., echo stdout for human-readable summary
 * lines while routing stderr through structured failure reporting.
 *
 * @api
 */
final readonly class ProcessResult
{
    /**
     * @param int    $exitCode Child process exit code (0 conventionally indicates success).
     * @param string $stdout   Bytes the child wrote to stdout, captured verbatim.
     * @param string $stderr   Bytes the child wrote to stderr, captured verbatim.
     */
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }
}
