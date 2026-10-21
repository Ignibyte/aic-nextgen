<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\ProcessJob;
use Gates\Domain\ProcessPool;
use Gates\Domain\ProcessResult;
use Override;
use RuntimeException;

use function array_diff_key;
use function array_slice;
use function array_values;
use function bin2hex;
use function count;
use function file_get_contents;
use function is_resource;
use function ksort;
use function max;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;
use function usleep;

/**
 * Production `ProcessPool`: `proc_open` children, at most `$jobs` at once, each
 * under `nice`, polled with `proc_get_status` until it exits.
 *
 * Output goes to capture files, not pipes, for the reason `SystemProcessRunner`
 * gives: a child never blocks on a full pipe the parent is not draining. Stdin
 * is `/dev/null`, so there is no pipe to close either.
 */
final readonly class ProcProcessPool implements ProcessPool
{
    /**
     * How long to wait between polls of the running children, in microseconds.
     */
    private const int POLL_INTERVAL = 20_000;

    /**
     * @param int $jobs     How many children may run at once; at least 1.
     * @param int $niceness The `nice -n` adjustment every child runs under.
     */
    public function __construct(
        private int $jobs,
        private int $niceness,
    ) {
    }

    /**
     * @param list<ProcessJob> $jobs The commands to run.
     *
     * @throws RuntimeException When a child process cannot be opened.
     *
     * @return list<ProcessResult> One result per job, in job order.
     */
    #[Override]
    public function run(array $jobs): array
    {
        $results = [];
        $running = [];

        while (count($results) < count($jobs)) {
            $running = $this->fill($jobs, $running, count($results) + count($running));
            $finished = $this->collect($running);
            $results += $finished;
            $running = array_diff_key($running, $finished);

            if ($finished !== []) {
                continue;
            }

            usleep(self::POLL_INTERVAL);
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * Start the next jobs, in order, until the cap is reached or none are left.
     *
     * @param list<ProcessJob>                                                     $jobs    Every job.
     * @param array<int, array{process: resource, stdout: string, stderr: string}> $running Running children by job index.
     * @param int                                                                  $started How many jobs have been started.
     *
     * @throws RuntimeException When a child process cannot be opened.
     *
     * @return array<int, array{process: resource, stdout: string, stderr: string}> The running children, the new ones added.
     */
    private function fill(array $jobs, array $running, int $started): array
    {
        foreach (array_slice($jobs, $started, max(0, $this->jobs - count($running)), true) as $index => $processJob) {
            $running[$index] = $this->start($processJob);
        }

        return $running;
    }

    /**
     * Start one job under `nice`, its output in two capture files.
     *
     * @param ProcessJob $processJob The job.
     *
     * @throws RuntimeException When the child process cannot be opened.
     *
     * @return array{process: resource, stdout: string, stderr: string} The child and its capture files.
     */
    private function start(ProcessJob $processJob): array
    {
        $capture = sys_get_temp_dir() . '/aic-pool-' . bin2hex(random_bytes(16));
        $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['file', $capture . '.out', 'w'], 2 => ['file', $capture . '.err', 'w']];
        $command = ['nice', '-n', (string) $this->niceness, ...$processJob->command];
        $process = proc_open($command, $descriptors, $pipes, $processJob->cwd, $processJob->env);

        if (!is_resource($process) || $pipes !== []) {
            throw new RuntimeException('Failed to open process for command: ' . ($processJob->command[0] ?? ''));
        }

        return ['process' => $process, 'stdout' => $capture . '.out', 'stderr' => $capture . '.err'];
    }

    /**
     * The results of the children that have exited since the last poll.
     *
     * @param array<int, array{process: resource, stdout: string, stderr: string}> $running Running children by job index.
     *
     * @return array<int, ProcessResult> Results by job index.
     */
    private function collect(array $running): array
    {
        $finished = [];

        foreach ($running as $index => $child) {
            $status = proc_get_status($child['process']);

            if ($status['running']) {
                continue;
            }

            $exitCode = $status['signaled']
                ? 128 + $status['termsig']
                : $status['exitcode'];
            proc_close($child['process']);
            $finished[$index] = new ProcessResult($exitCode, $this->drain($child['stdout']), $this->drain($child['stderr']));
        }

        return $finished;
    }

    /**
     * Read a capture file and remove it.
     *
     * @param string $path The capture file.
     *
     * @return string What the child wrote there.
     */
    private function drain(string $path): string
    {
        $contents = file_get_contents($path);
        unlink($path);

        return $contents === false
            ? ''
            : $contents;
    }
}
