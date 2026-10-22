<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\RunHost;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_filter;
use function array_unique;
use function array_values;
use function bin2hex;
use function explode;
use function fclose;
use function is_resource;
use function max;
use function preg_match;
use function proc_close;
use function proc_open;
use function random_bytes;
use function sort;
use function stream_get_contents;
use function stream_isatty;
use function trim;

use const STDIN;

/**
 * The machine a run runs on (#100): git's HEAD in the project, whether
 * standard input is a terminal, ids from `random_bytes`, and git's view of
 * what changed (#101).
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
        $output = trim((string) $this->git(['rev-parse', '--verify', 'HEAD']));

        return preg_match('/^[0-9a-f]{40,64}$/', $output) === 1
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

    /**
     * @param string|null $baseCommit The base commit; null when the run had none.
     *
     * @throws WorkflowRefusal When git cannot list them: a verdict on an unknown change set would be a guess.
     *
     * @return list<string> `git diff --name-only` against it with the working tree, and the untracked files git does not
     *                      ignore; every tracked and untracked file without a base.
     */
    #[Override]
    public function changedFiles(?string $baseCommit): array
    {
        $listed = $baseCommit === null
            ? $this->listed(['ls-files', '-z', '--cached', '--others', '--exclude-standard'])
            : $this->listed(['diff', '--name-only', '--no-renames', '-z', $baseCommit, '--'])
                . $this->listed(['ls-files', '-z', '--others', '--exclude-standard']);
        $paths = array_values(array_unique(array_filter(explode("\0", $listed), static fn (string $path): bool => $path !== '')));
        sort($paths);

        return $paths;
    }

    /**
     * @param string $baseCommit A commit.
     * @param string $path       A path in the project.
     *
     * @return string|null `git show <commit>:<path>`; null when it did not exist there.
     */
    #[Override]
    public function fileAt(string $baseCommit, string $path): ?string
    {
        return preg_match('/^[0-9a-f]{40,64}$/', $baseCommit) === 1
            ? $this->git(['show', $baseCommit . ':' . $path])
            : null;
    }

    /**
     * @param list<string> $arguments A git command that lists paths.
     *
     * @throws WorkflowRefusal When it fails.
     *
     * @return string Its NUL-separated list.
     */
    private function listed(array $arguments): string
    {
        return $this->git($arguments)
            ?? throw new WorkflowRefusal('git ' . $arguments[0] . ' failed in the project, so its changes are unknown.');
    }

    /**
     * @param list<string> $arguments git's arguments.
     *
     * @return string|null Its standard output when it exits 0; null otherwise. Its standard error is dropped.
     */
    private function git(array $arguments): ?string
    {
        $pipes = [];
        $process = proc_open(
            ['git', ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $this->root,
        );

        if (!is_resource($process)) {
            return null;
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return proc_close($process) === 0
            ? $output
            : null;
    }
}
