<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;

use function array_find;
use function fwrite;
use function implode;
use function is_executable;
use function is_string;
use function mb_trim;
use function sprintf;

use const PHP_EOL;

/**
 * Locates and runs an external tool binary for the gates that wrap one
 * (gitleaks, gate:17; semgrep, gate:15).
 *
 * Shared so the binary lookup (known paths first, then the PATH) and the
 * run-and-echo step live in one place.
 *
 * @api
 */
final readonly class ExternalTool
{
    /**
     * @param ProcessRunner $processRunner Seam wrapping `proc_open`.
     */
    public function __construct(
        private ProcessRunner $processRunner,
    ) {
    }

    /**
     * Locate a binary: the first executable candidate, else `command -v <name>` on the PATH.
     *
     * @param string       $name       Binary name looked up on the PATH.
     * @param list<string> $candidates Absolute paths checked first, in order.
     *
     * @return string|null The binary path, or null when none is found.
     */
    public function locate(string $name, array $candidates): ?string
    {
        $local = array_find($candidates, static fn (string $candidate): bool => is_executable($candidate));

        if (is_string($local)) {
            return $local;
        }

        $processResult = $this->processRunner->run(['/bin/sh', '-c', 'command -v ' . $name]);
        $found = mb_trim($processResult->stdout);

        if ($found === '') {
            return null;
        }

        return $found;
    }

    /**
     * Run a command, echo it and its output under the gate's label, and return its exit code.
     *
     * @param string       $gate    Gate label for the echoed command line, e.g. `gate:15`.
     * @param list<string> $command Array-form command for `proc_open`.
     * @param resource     $stdout  Stream the command line and its stdout are written to.
     * @param resource     $stderr  Stream the command's stderr is written to.
     *
     * @return int The command's exit code.
     */
    public function run(string $gate, array $command, mixed $stdout, mixed $stderr): int
    {
        $processResult = $this->processRunner->run($command);
        fwrite($stdout, sprintf('%s — %s%s%s', $gate, implode(' ', $command), PHP_EOL, $processResult->stdout));
        fwrite($stderr, $processResult->stderr);

        return $processResult->exitCode;
    }
}
