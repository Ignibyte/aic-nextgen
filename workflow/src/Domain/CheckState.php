<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What the evidence store holds a check as (#101, Droost's `CheckState`):
 * satisfied or blocked when it was measured; skipped when it deferred;
 * errored when it did not run to a verdict.
 */
enum CheckState: string
{
    case Satisfied = 'satisfied';
    case Blocked = 'blocked';
    case Skipped = 'skipped';
    case Errored = 'errored';

    /**
     * @return CheckStatus The report row's status for it: a skip is deferred, an error fails.
     */
    public function checkStatus(): CheckStatus
    {
        return match ($this) {
            self::Satisfied => CheckStatus::Passed,
            self::Skipped => CheckStatus::Deferred,
            self::Blocked, self::Errored => CheckStatus::Failed,
        };
    }
}
