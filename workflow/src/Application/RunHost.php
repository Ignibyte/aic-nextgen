<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * What a run asks of the machine it runs on (#100): the commit, whether an
 * operator is at a terminal, fresh ids, and what changed since the base
 * commit (#101).
 */
interface RunHost
{
    /**
     * @return string|null The project's HEAD commit; null outside a git repository.
     */
    public function baseCommit(): ?string;

    /**
     * @return bool Whether standard input is a terminal: an operator, not an agent's shell.
     */
    public function operatorTerminal(): bool;

    /**
     * @param string $prefix `run` or `evt`.
     * @param int    $bytes  How many random bytes, written as twice as many hex digits.
     *
     * @return string A fresh id: the prefix, a hyphen, the hex.
     */
    public function newId(string $prefix, int $bytes): string;

    /**
     * @param string|null $baseCommit The run's base commit; null when it had none.
     *
     * @throws WorkflowRefusal When the changes cannot be listed.
     *
     * @return list<string> The project's changed paths against it, sorted: the tracked diff with the working tree, deleted
     *                      paths included, and untracked files git does not ignore (#101). Without a base, every
     *                      tracked and untracked file.
     */
    public function changedFiles(?string $baseCommit): array;

    /**
     * @param string $baseCommit A commit.
     * @param string $path       A path in the project.
     *
     * @return string|null The file's content at that commit; null when it did not exist there (#101).
     */
    public function fileAt(string $baseCommit, string $path): ?string;
}
