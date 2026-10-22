<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use AIC\Workflow\Domain\Guard\Enforcement;

/**
 * What a run is from its start (#100): its id, when it began, the commit it
 * began on, the spec it reads, and the guard's enforcement, frozen at begin
 * (#102).
 */
final readonly class RunStart
{
    /**
     * @param string      $runId       `run-` and 12 hex digits.
     * @param string      $startedAt   When it began, UTC `Y-m-d\TH:i:s\Z`.
     * @param string|null $baseCommit  The commit it began on; null outside a git repository.
     * @param string      $specPath    The spec plan writes, relative to the project: `var/workflow/spec-<n>.md`.
     * @param Enforcement $enforcement How hard the guard holds the run; frozen when it began.
     */
    public function __construct(
        public string $runId,
        public string $startedAt,
        public ?string $baseCommit,
        public string $specPath,
        public Enforcement $enforcement = Enforcement::Hard,
    ) {
    }
}
