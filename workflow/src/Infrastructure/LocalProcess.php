<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\ProcessRunner;
use AIC\Workflow\Domain\ProcessResult;
use Override;

use function fclose;
use function feof;
use function fread;
use function fwrite;
use function is_resource;
use function microtime;
use function proc_close;
use function proc_open;
use function proc_terminate;
use function stream_select;
use function stream_set_blocking;

/**
 * Runs a process in the project (#101) through `proc_open` with an argument
 * list, never a shell: standard error and output are read together, in the order they come, standard input
 * is what the caller hands it (#104), the environment is the engine's, and a process that outlives its timeout is stopped and reads as
 * exit 124.
 */
final readonly class LocalProcess implements ProcessRunner
{
    /**
     * @param string $root The project's root, the processes' working directory.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param list<string> $argv    The program and its arguments.
     * @param int          $timeout Seconds before it is stopped.
     * @param string       $input   What it reads on stdin.
     *
     * @return ProcessResult Its exit code and output; 127 when it could not start, 124 when it was stopped.
     */
    #[Override]
    public function run(array $argv, int $timeout, string $input = ''): ProcessResult
    {
        $pipes = [];
        $process = proc_open($argv, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);

        if (!is_resource($process)) {
            return new ProcessResult(127, '');
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = $this->drain([$pipes[1], $pipes[2]], microtime(true) + $timeout);
        $timedOut = !feof($pipes[1]) || !feof($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($timedOut) {
            proc_terminate($process);
            proc_close($process);

            return new ProcessResult(124, $output);
        }

        return new ProcessResult(proc_close($process), $output);
    }

    /**
     * @param list<resource> $streams  The process's output and error, not blocking.
     * @param float          $deadline When to stop reading, on the `microtime` clock.
     *
     * @return string What they wrote, in the order it came.
     */
    private function drain(array $streams, float $deadline): string
    {
        $output = '';

        while ($streams !== [] && microtime(true) < $deadline) {
            $output .= $this->ready($streams);
            $streams = $this->open($streams);
        }

        return $output;
    }

    /**
     * @param list<resource> $streams Open streams.
     *
     * @return string What the readable ones hold now, after waiting a second at most.
     */
    private function ready(array $streams): string
    {
        $read = $streams;
        $write = null;
        $except = null;
        $output = '';

        if (stream_select($read, $write, $except, 1) === false) {
            return '';
        }

        foreach ($read as $stream) {
            $output .= (string) fread($stream, 65_536);
        }

        return $output;
    }

    /**
     * @param list<resource> $streams Streams.
     *
     * @return list<resource> Those not at their end.
     */
    private function open(array $streams): array
    {
        $open = [];

        foreach ($streams as $stream) {
            if (feof($stream)) {
                continue;
            }

            $open[] = $stream;
        }

        return $open;
    }
}
