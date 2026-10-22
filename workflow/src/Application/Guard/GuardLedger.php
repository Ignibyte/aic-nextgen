<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\GuardRow;

/**
 * The guard's ledger (#102): one row per hook call, appended by the hook,
 * read by the engine. A row that cannot be written is lost, never a
 * refusal.
 */
interface GuardLedger
{
    /**
     * @param GuardRow $guardRow A call and its verdict.
     */
    public function append(GuardRow $guardRow): void;

    /**
     * @return list<GuardRow> Every row, oldest first; a line that does not read as a row is skipped.
     */
    public function rows(): array;

    /**
     * @param string $run   A run.
     * @param string $phase Its phase.
     * @param string $rule  A rule.
     *
     * @return bool Whether the rule already nudged at that run and phase.
     */
    public function warned(string $run, string $phase, string $rule): bool;
}
