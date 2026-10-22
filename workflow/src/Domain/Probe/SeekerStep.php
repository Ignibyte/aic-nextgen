<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

/**
 * Files a clean seeker ledger for the open run the way the hook would (#119,
 * #127): the ledger, naming the brief's subject, handed to the guard's
 * `returned` verb as an `Agent` result, then piped into
 * `workflow:seeker-report`. It proves the filing path, not the seeker's
 * judgement; the seeker probe measures that. It holds when the report is
 * filed.
 */
final readonly class SeekerStep implements ProbeStep
{
    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'file the seeker';
    }

    /**
     * @return int One.
     */
    #[Override]
    public function expectations(): int
    {
        return 1;
    }
}
