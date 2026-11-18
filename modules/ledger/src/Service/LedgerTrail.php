<?php

declare(strict_types=1);

namespace App\Ledger\Service;

/**
 * What GET /ledger/guards saw, step by step (#310). Each step's writes are
 * rolled back, so the lines are the only thing a step keeps.
 */
final class LedgerTrail
{
    /**
     * The lines so far, in order.
     *
     * @var list<string>
     */
    private array $lines = [];

    /**
     * @param string $step    What was tried or read.
     * @param string $outcome What came of it.
     */
    public function note(string $step, string $outcome): void
    {
        $this->lines[] = $step . ': ' . $outcome;
    }

    /**
     * @return list<string> The lines, in order.
     */
    public function lines(): array
    {
        return $this->lines;
    }
}
