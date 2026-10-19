<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Closure;
use Gates\Domain\ProcessResult;
use Gates\Domain\ProcessRunner;
use Override;
use RuntimeException;

use function bin2hex;
use function count;
use function fclose;
use function file_get_contents;
use function is_resource;
use function proc_close;
use function proc_open;
use function random_bytes;
use function restore_error_handler;
use function set_error_handler;
use function sys_get_temp_dir;
use function unlink;

/**
 * Production `ProcessRunner` backed by `proc_open` with stdout/stderr routed to tempfile descriptors.
 *
 * Why `proc_open` and not `passthru`/`exec`: tests need stdout and stderr captured independently.
 * `passthru` writes both streams directly to the parent process's stdout/stderr with no capture;
 * `exec` returns stdout joined as an array but discards stderr entirely.
 *
 * Why `file` descriptors instead of `pipe` descriptors: pipe buffering causes a deadlock when the
 * child writes more than the kernel pipe buffer (typically 64 KB) before exiting — the child blocks
 * on a full pipe while the parent serially reads the other pipe. Routing stdout/stderr to OS files
 * sidesteps the buffer entirely: the kernel handles file writes asynchronously, the child never
 * blocks, and the parent reads after `proc_close` returns. No concurrent drain loop, no
 * `stream_set_blocking` toggle, no `stream_select` polling, and so no deadlock surface.
 *
 * Why no Symfony Process: AIC keeps a strict PSR-only require list; the native PHP API does what
 * we need.
 */
final class SystemProcessRunner implements ProcessRunner
{
    /**
     * Tempfile prefix for the per-call stdout capture file.
     */
    private const string STDOUT_TEMPFILE_PREFIX = 'aic-spr-out-';

    /**
     * Tempfile prefix for the per-call stderr capture file.
     */
    private const string STDERR_TEMPFILE_PREFIX = 'aic-spr-err-';

    /**
     * Bytes of `random_bytes` entropy mixed into each capture-file basename.
     */
    private const int RANDOM_SUFFIX_BYTES = 16;

    /**
     * Run the command synchronously and capture stdout + stderr in full.
     *
     * @param list<string>          $command Command + arguments (no shell string — `proc_open`'s array form).
     * @param string|null           $cwd     Working directory for the child process; null inherits.
     * @param array<string, string> $env     Environment variables for the child; empty inherits the parent's environment.
     *
     * @throws RuntimeException When the child process cannot be opened.
     */
    #[Override]
    public function run(array $command, ?string $cwd = null, array $env = []): ProcessResult
    {
        $envOrInherit = count($env) < 1
            ? null
            : $env;
        $captureFiles = $this->allocateCaptureFiles();
        $opened = $this->openProcess($command, $cwd, $envOrInherit, $captureFiles);
        $process = $opened['process'];

        if (!is_resource($process)) {
            $this->removeCaptureFiles($captureFiles);

            throw new RuntimeException(
                'Failed to open process for command: ' . $command[0] . ': ' . $opened['captured'],
            );
        }

        return $this->closeProcessAndReadCaptures($process, $opened['pipes'], $captureFiles);
    }

    /**
     * Compose two capture-file paths under `sys_get_temp_dir()`.
     *
     * No `tempnam` — `proc_open`'s `['file', $path, 'w']` descriptor creates the file on open, so
     * pre-allocation is unnecessary. The basename mixes a `random_bytes` suffix for collision
     * safety across concurrent test runs.
     *
     * @return array{stdout: string, stderr: string} Absolute paths for the upcoming `proc_open` call.
     */
    private function allocateCaptureFiles(): array
    {
        $tempDir = sys_get_temp_dir();
        $suffix = bin2hex(random_bytes(self::RANDOM_SUFFIX_BYTES));

        return [
            'stderr' => $tempDir . '/' . self::STDERR_TEMPFILE_PREFIX . $suffix,
            'stdout' => $tempDir . '/' . self::STDOUT_TEMPFILE_PREFIX . $suffix,
        ];
    }

    /**
     * Spawn the child process with stdout/stderr routed to the supplied tempfiles.
     *
     * Uses the libxml-style error-handler dance to capture the proc_open warning text for
     * inclusion in a downstream RuntimeException — `set_error_handler` then `restore_error_handler`
     * inside a try/finally block.
     *
     * @param list<string>                          $command      Command + arguments.
     * @param string|null                           $cwd          Working directory.
     * @param array<string, string>|null            $envOrInherit Resolved env (null inherits the parent's).
     * @param array{stdout: string, stderr: string} $captureFiles Tempfile paths bound to descriptors 1/2.
     *
     * @return array{process: resource|false, pipes: array<int, resource>, captured: string}
     */
    private function openProcess(array $command, ?string $cwd, ?array $envOrInherit, array $captureFiles): array
    {
        $warningCapture = new WarningCapture();
        set_error_handler($this->makeWarningCapturer($warningCapture));
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $captureFiles['stdout'], 'w'],
            2 => ['file', $captureFiles['stderr'], 'w'],
        ];

        try {
            $pipes = [];
            $process = proc_open($command, $descriptors, $pipes, $cwd, $envOrInherit);

            return ['process' => $process, 'pipes' => $pipes, 'captured' => $warningCapture->read()];
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Build the warning-capturing error handler — kept static so the closure does not retain a
     * `$this` reference.
     *
     * @param WarningCapture $warningCapture Slot with a mutable `message` property.
     *
     * @return Closure(int, string): bool
     */
    private function makeWarningCapturer(WarningCapture $warningCapture): Closure
    {
        return static function (int $severity, string $message) use ($warningCapture): bool {
            unset($severity);
            $warningCapture->record($message);

            return true;
        };
    }

    /**
     * Close stdin pipe, terminate the process, then read both capture files and remove them.
     *
     * @param resource                              $process      Handle returned by `proc_open`.
     * @param array<int, resource>                  $pipes        Pipe handles (only descriptor 0 — stdin — is a pipe).
     * @param array{stdout: string, stderr: string} $captureFiles Tempfile paths to read and clean up.
     */
    private function closeProcessAndReadCaptures(mixed $process, array $pipes, array $captureFiles): ProcessResult
    {
        fclose($pipes[0]);
        $exitCode = proc_close($process);

        $stdoutContents = file_get_contents($captureFiles['stdout']);
        $stderrContents = file_get_contents($captureFiles['stderr']);
        $this->removeCaptureFiles($captureFiles);

        return new ProcessResult(
            $exitCode,
            $stdoutContents === false ? '' : $stdoutContents,
            $stderrContents === false ? '' : $stderrContents,
        );
    }

    /**
     * Best-effort removal of the two capture tempfiles.
     *
     * @param array{stdout: string, stderr: string} $captureFiles Tempfile paths to remove.
     */
    private function removeCaptureFiles(array $captureFiles): void
    {
        unlink($captureFiles['stdout']);
        unlink($captureFiles['stderr']);
    }
}
