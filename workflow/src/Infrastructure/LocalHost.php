<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\RunHost;
use Override;

use function bin2hex;
use function fclose;
use function is_resource;
use function max;
use function preg_match;
use function proc_close;
use function proc_open;
use function random_bytes;
use function stream_get_contents;
use function stream_isatty;
use function trim;

use const STDIN;

/**
 * The machine a run runs on (#100): git's HEAD in the project, whether
 * standard input is a terminal, and ids from `random_bytes`.
 */
final readonly class LocalHost implements RunHost
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return string|null `git rev-parse --verify HEAD` in the project; null outside a git repository.
     */
    #[Override]
    public function baseCommit(): ?string
    {
        $pipes = [];
        $process = proc_open(['git', 'rev-parse', '--verify', 'HEAD'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);

        if (!is_resource($process)) {
            return null;
        }

        $output = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0 && preg_match('/^[0-9a-f]{40,64}$/', $output) === 1
            ? $output
            : null;
    }

    /**
     * @return bool Whether standard input is a terminal.
     */
    #[Override]
    public function operatorTerminal(): bool
    {
        return stream_isatty(STDIN);
    }

    /**
     * @param string $prefix `run` or `evt`.
     * @param int    $bytes  How many random bytes.
     *
     * @return string The prefix, a hyphen, the bytes in hex.
     */
    #[Override]
    public function newId(string $prefix, int $bytes): string
    {
        return $prefix . '-' . bin2hex(random_bytes(max(1, $bytes)));
    }
}
