<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\SeekerFinding;
use AIC\Workflow\Domain\SeekerInspection;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * What the seeker reads its scope from (#103): the engine's word on the open
 * run, never the dispatch prompt's. The run, its ticket and phase, the base
 * commit, the spec, the subject the ledger must name, the changed paths, the
 * phase's last report, the previous counted round's findings and the
 * checklist.
 */
final readonly class SeekerBrief
{
    /**
     * @param SeekerRun       $seekerRun       The open run.
     * @param RunSubject      $runSubject      The subject, and which changes are the engine's own.
     * @param RunHost         $runHost         The changed paths.
     * @param SeekerLog       $seekerLog       The previous round.
     * @param SeekerChecklist $seekerChecklist Where the checklist is.
     */
    public function __construct(
        private SeekerRun $seekerRun,
        private RunSubject $runSubject,
        private RunHost $runHost,
        private SeekerLog $seekerLog,
        private SeekerChecklist $seekerChecklist,
    ) {
    }

    /**
     * @throws WorkflowRefusal When no run is open past plan, or it cannot be read.
     *
     * @return list<string> The brief, JSON, a line each.
     */
    public function brief(): array
    {
        [$runState, $phase] = $this->seekerRun->open();
        $runStart = $runState->runStart;
        $flags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return explode("\n", json_encode([
            'base_commit' => $runStart->baseCommit,
            'changed' => $this->changed($runState),
            'checklist' => $this->seekerChecklist->path(),
            'phase' => $phase->value,
            'previous' => $this->previous($runStart->runId),
            'report' => $this->report($runState->reports[$phase->value] ?? null),
            'run_id' => $runStart->runId,
            'spec' => $runStart->specPath,
            'subject' => $this->runSubject->hash($runState),
            'ticket' => $runState->ticket->toArray(),
        ], $flags));
    }

    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When the changes cannot be listed.
     *
     * @return list<string> The paths the run changed, the engine's own writes left out.
     */
    private function changed(RunState $runState): array
    {
        return array_values(array_filter(
            $this->runHost->changedFiles($runState->runStart->baseCommit),
            fn (string $path): bool => !$this->runSubject->engineWrote($path),
        ));
    }

    /**
     * @param PhaseReport|null $phaseReport The phase's last report.
     *
     * @return list<array{check: string, status: string, why: string, remedy: string}> Its rows.
     */
    private function report(?PhaseReport $phaseReport): array
    {
        return $phaseReport instanceof PhaseReport
            ? array_map(static fn (CheckRow $checkRow): array => $checkRow->toArray(), $phaseReport->rows)
            : [];
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return array{round: int, findings: list<array<string, string>>}|null The previous counted round, which the next
     *                                                                       ledger restates whole; null before the first.
     */
    private function previous(string $runId): ?array
    {
        $seekerInspection = $this->seekerLog->latestCounted($runId);

        return $seekerInspection instanceof SeekerInspection
            ? [
                'findings' => array_map(static fn (SeekerFinding $seekerFinding): array => [
                    'finding' => $seekerFinding->finding,
                    'item' => $seekerFinding->item,
                    'location' => $seekerFinding->location,
                    'ref' => $seekerFinding->ref,
                    'severity' => $seekerFinding->seekerSeverity->value,
                    'status' => $seekerFinding->seekerStatus->value,
                ], $seekerInspection->seekerLedger->findings),
                'round' => $seekerInspection->round,
            ]
            : null;
    }
}
