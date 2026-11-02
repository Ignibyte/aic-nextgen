<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * One database statement a profiled request ran (#229): its SQL with its
 * placeholders, never the values bound to them.
 */
final readonly class StatementRecord
{
    /**
     * @param string $sql          The statement as written, `?` placeholders and all; `BEGIN`, `COMMIT` or
     *                             `ROLLBACK` for a transaction's step.
     * @param int    $microseconds How long it took.
     * @param int    $rows         The rows it read or changed.
     */
    public function __construct(
        public string $sql,
        public int $microseconds,
        public int $rows,
    ) {
    }
}
