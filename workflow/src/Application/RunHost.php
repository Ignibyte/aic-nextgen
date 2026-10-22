<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

/**
 * What a run asks of the machine it runs on (#100): the commit, whether an
 * operator is at a terminal, and fresh ids.
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
}
