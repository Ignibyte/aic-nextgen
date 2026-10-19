<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Seam between gate-script implementations and the operating-system process API.
 *
 * Tool-invoking gate scripts (captainhook, composer-audit, normalize,
 * deduplicator, gitleaks, semgrep, shellcheck) MUST shell out via this
 * interface — never via `passthru`, `exec`, `shell_exec`, or `proc_open`
 * directly — so every process launch goes through one audited seam.
 *
 * The default production implementation is `Gates\Infrastructure\SystemProcessRunner`,
 * which uses `proc_open` with separate stdout/stderr pipes.
 *
 * @api
 */
interface ProcessRunner
{
    /**
     * Run a command and return its captured result.
     *
     * Implementations MUST capture stdout and stderr separately. Implementations
     * MUST NOT call PHP's `exit()` regardless of the child process's exit code —
     * the caller decides what to do with a non-zero exit. Implementations MUST
     * NOT mutate the parent process's working directory permanently; if `$cwd`
     * is non-null, the child process runs in that directory but the parent's
     * `getcwd()` is unaffected.
     *
     * @param list<string>          $command Command + arguments. Element 0 is the executable
     *                                       (already resolved to a path or a name on PATH); elements
     *                                       1..N are the arguments. NOT a shell string — escaping
     *                                       is the implementation's responsibility.
     * @param string|null           $cwd     Working directory for the child process; null inherits
     *                                       the parent's cwd.
     * @param array<string, string> $env     Environment variables to expose to the child;
     *                                       passed verbatim with no inheritance from the parent.
     */
    public function run(array $command, ?string $cwd = null, array $env = []): ProcessResult;
}
