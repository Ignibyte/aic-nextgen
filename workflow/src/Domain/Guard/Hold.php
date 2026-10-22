<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

/**
 * What a run waits on (#102): `run.json`'s `awaiting`. #102 has one kind,
 * `operator`: a phase only the operator can clear. #117 adds the others.
 */
final readonly class Hold
{
    /**
     * @param string $kind  `operator`.
     * @param string $phase The phase that is held.
     * @param string $why   What the operator must do, from the holding rows.
     * @param string $time  When it began, UTC.
     */
    public function __construct(
        public string $kind,
        public string $phase,
        public string $why,
        public string $time,
    ) {
    }

    /**
     * @return array{at: string, kind: string, phase: string, why: string} Its `run.json` and event form.
     */
    public function toArray(): array
    {
        return ['at' => $this->time, 'kind' => $this->kind, 'phase' => $this->phase, 'why' => $this->why];
    }
}
