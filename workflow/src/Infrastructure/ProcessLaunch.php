<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use function fclose;
use function is_resource;
use function proc_close;
use function proc_get_status;
use function proc_open;

use const PHP_BINARY;

/** Opens an unacknowledged helper, which cannot start a command until ownership is verified. */
final readonly class ProcessLaunch
{
    /**
     * @param string       $root  Working directory inherited by the command.
     * @param list<string> $argv  Literal command arguments.
     * @param string       $input Command input.
     *
     * @return ProcessSession|null The owned helper, or null when opening it failed.
     */
    public function open(string $root, array $argv, string $input): ?ProcessSession
    {
        $pipes = [];
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => ['pipe', 'w'], 4 => ['pipe', 'r']];
        $process = proc_open([PHP_BINARY, __DIR__ . '/../../bin/process-session.php', ...$argv], $descriptors, $pipes, $root);

        if (!is_resource($process)) {
            return null;
        }

        return $this->session([$process], $pipes, $input);
    }

    /**
     * @param array{0: resource}         $process The unacknowledged helper.
     * @param array<array-key, resource> $pipes   Its descriptors.
     * @param string                     $input   Command input.
     *
     * @return ProcessSession|null A still-unreaped helper, or null after early launch failure.
     */
    private function session(array $process, array $pipes, string $input): ?ProcessSession
    {
        // A terminal status read can reap; never retain that PID as a signal target.
        $status = proc_get_status($process[0]);

        if (!$status['running']) {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            proc_close($process[0]);

            return null;
        }

        return new ProcessSession($process, new ProcessGroup($status['pid']), new ProcessStreams($pipes, $input));
    }
}
