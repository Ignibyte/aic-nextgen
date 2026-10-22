<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use AIC\Workflow\Domain\Guard\Enforcement;

/**
 * What a run is from its start (#100): its id, when it began, the commit it
 * began on, the spec it reads, the guard's enforcement (#102) and the house
 * rules' files (#104), its mode and whether the operator confirms complete
 * (#117), all frozen at begin.
 */
final readonly class RunStart
{
    /**
     * @param string                     $runId         `run-` and 12 hex digits.
     * @param string                     $startedAt     When it began, UTC `Y-m-d\TH:i:s\Z`.
     * @param string|null                $baseCommit    The commit it began on; null outside a git repository.
     * @param string                     $specPath      The spec plan writes, relative to the project: `var/workflow/spec-<n>.md`.
     * @param Enforcement                $enforcement   How hard the guard holds the run; frozen when it began.
     * @param array<string, string|null> $ruleFiles     The house rules' files and config/workflow.php by path, each with its
     *                                                  sha256 when the run began; null for one that was missing (#104).
     * @param Mode                       $mode          Agentic or interactive (#117).
     * @param bool                       $humanComplete Whether the operator confirms complete in an agentic run (#117).
     */
    public function __construct(
        public string $runId,
        public string $startedAt,
        public ?string $baseCommit,
        public string $specPath,
        public Enforcement $enforcement = Enforcement::Hard,
        public array $ruleFiles = [],
        public Mode $mode = Mode::Agentic,
        public bool $humanComplete = false,
    ) {
    }
}
