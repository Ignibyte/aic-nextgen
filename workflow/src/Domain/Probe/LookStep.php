<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

/**
 * Makes plan's code lookups for a spec in the app (#119, #118), with the
 * builder's `look`, so plan's grounding check sees the run looked. It holds
 * when the lookups ran.
 */
final readonly class LookStep implements ProbeStep
{
    /**
     * @param string $spec The spec's path in the app.
     */
    public function __construct(
        public string $spec = 'var/workflow/spec-1.md',
    ) {
    }

    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'look ' . $this->spec;
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
