<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\GateRun;
use AIC\Workflow\Domain\GateStatus;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_intersect;
use function implode;

/**
 * The gate at a phase exit (#101). Code and complete run `composer run gate`
 * every time; check reuses the run's last gate verdict only while it is green
 * and fresh on the tree as it is, and runs the gate again otherwise. A deferral is recorded and not
 * a pass; at complete a deferral of gate:21 or gate:23 holds the run. When
 * one check has failed `maxGateRetries` times at the phase, the gate no
 * longer runs there: the phase is the operator's.
 */
final readonly class GateCheck implements PhaseCheck
{
    /**
     * The gates whose deferral holds complete: the native ones.
     */
    private const array NATIVE = ['gate:21', 'gate:23'];

    /**
     * What to do when a gate failed.
     */
    private const string FIX = 'fix each at its source (no baselines, no suppressions), then run bin/aic workflow:run again.';

    /**
     * What to do when the environment kept the gate from a verdict.
     */
    private const string ENVIRONMENT = 'bring up what the gate needs (tools, compiler, database), then run bin/aic workflow:run again.';

    /**
     * What to do when the gate could not start yet.
     */
    private const string AGAIN = 'wait for it to end, then run bin/aic workflow:run again.';

    /**
     * @param Phase            $phase            The phase it runs at: code, check or complete.
     * @param GateRunner       $gateRunner       Runs and records the gate.
     * @param GateExecutor     $gateExecutor     Whether a gate run is live.
     * @param EvidenceLog      $evidenceLog      The verdicts so far.
     * @param EvidenceRecorder $evidenceRecorder The row for a verdict that was not recorded.
     * @param RunSubject       $runSubject       The tree's subject hash.
     * @param int              $maxGateRetries   How often one check may fail at the phase.
     */
    public function __construct(
        private Phase $phase,
        private GateRunner $gateRunner,
        private GateExecutor $gateExecutor,
        private EvidenceLog $evidenceLog,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
        private int $maxGateRetries,
    ) {
    }

    /**
     * @return Phase The phase it runs at.
     */
    #[Override]
    public function phase(): Phase
    {
        return $this->phase;
    }

    /**
     * @param RunState $runState The run, at the phase.
     *
     * @return CheckRow The gate's row.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        try {
            return $this->judge($runState);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->evidenceRecorder->unrecorded('gate', $workflowRefusal);
        }
    }

    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When the store cannot be read or written.
     *
     * @return CheckRow The gate's row.
     */
    private function judge(RunState $runState): CheckRow
    {
        $runId = $runState->runStart->runId;
        $gates = $this->evidenceLog->frozen($runId)->gates ?? [];
        $held = $this->held($runId, $gates);

        if ($held instanceof CheckRow) {
            return $held;
        }

        $subjectHash = $this->runSubject->hash($runState);
        $earlier = $this->earlier($runId);

        if ($earlier instanceof CheckRecord && $earlier->greenFor($subjectHash)) {
            return new CheckRow('gate', CheckStatus::Passed, 'the last gate verdict is fresh on this tree: ' . $earlier->summary, '');
        }

        $stale = $this->rerun($earlier, $subjectHash);

        return $this->gateExecutor->busy()
            ? new CheckRow('gate', CheckStatus::Blocked, 'another gate run is live on this machine.', self::AGAIN)
            : $this->row($this->gateRunner->run($runState, $this->phase, $gates, $subjectHash), $stale);
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return CheckRecord|null At check, the run's last gate verdict; null at code and complete, which always run the gate.
     */
    private function earlier(string $runId): ?CheckRecord
    {
        return $this->phase === Phase::Check
            ? $this->evidenceLog->latest($runId, 'gate')
            : null;
    }

    /**
     * @param CheckRecord|null $checkRecord The run's last gate verdict, at check; null at code and complete.
     * @param string           $subjectHash The tree now.
     *
     * @return string Why the gate runs again at check; empty when it runs as a matter of course.
     */
    private function rerun(?CheckRecord $checkRecord, string $subjectHash): string
    {
        if (!$checkRecord instanceof CheckRecord) {
            return '';
        }

        return $checkRecord->subjectHash === $subjectHash
            ? 'the last gate verdict was not green, so the gate ran again: '
            : 'the last gate verdict is stale (the tree changed), so the gate ran again: ';
    }

    /**
     * @param string       $runId The run.
     * @param list<string> $gates The gate names planning froze.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return CheckRow|null The row that holds the phase before the gate runs: retries spent, or no frozen gate list.
     */
    private function held(string $runId, array $gates): ?CheckRow
    {
        $spent = $this->spent($runId);

        return match (true) {
            $spent !== '' => $this->spentRow($spent),
            $gates === [] => new CheckRow(
                'gate',
                CheckStatus::Failed,
                'planning froze no gate list.',
                'the operator resets the run with bin/aic workflow:reset.',
            ),
            default => null,
        };
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return string The checks that have failed as often as the phase allows, joined; empty when none has.
     */
    private function spent(string $runId): string
    {
        $spent = [];

        foreach ($this->evidenceLog->failures($runId, $this->phase) as $name => $failures) {
            if ($name === 'gate' || $failures < $this->maxGateRetries) {
                continue;
            }

            $spent[] = $name . ' (' . $failures . ' times)';
        }

        return implode(', ', $spent);
    }

    /**
     * @param string $spent The checks whose retries are spent.
     *
     * @return CheckRow The row that ends the phase for the operator.
     */
    private function spentRow(string $spent): CheckRow
    {
        return new CheckRow(
            'gate',
            CheckStatus::Failed,
            'max_gate_retries (' . $this->maxGateRetries . ') is spent at ' . $this->phase->value . ': ' . $spent
                . ' failed; the gate does not run again.',
            'the operator decides: fix it by hand, or run bin/aic workflow:reset from a terminal.',
        );
    }

    /**
     * @param GateRun $gateRun What the gate found.
     * @param string  $stale   Why it ran again at check; empty otherwise.
     *
     * @return CheckRow The gate's row.
     */
    private function row(GateRun $gateRun, string $stale): CheckRow
    {
        $summary = $stale . $gateRun->summary;

        return match ($gateRun->gateStatus) {
            GateStatus::Passed => new CheckRow('gate', CheckStatus::Passed, $summary, ''),
            GateStatus::Deferred => $this->deferred($gateRun, $summary),
            GateStatus::Failed => new CheckRow(
                'gate',
                CheckStatus::Failed,
                implode(', ', $gateRun->named(GateStatus::Failed)) . ' failed: ' . $summary,
                self::FIX,
            ),
            GateStatus::ErrorToolMissing, GateStatus::ErrorToolFailed => new CheckRow(
                'gate',
                CheckStatus::Failed,
                $summary,
                self::ENVIRONMENT,
            ),
        };
    }

    /**
     * @param GateRun $gateRun A run with a deferral and no failure.
     * @param string  $summary Why.
     *
     * @return CheckRow Deferred, which does not hold the phase; failed at complete when a native gate deferred.
     */
    private function deferred(GateRun $gateRun, string $summary): CheckRow
    {
        $native = array_intersect($gateRun->named(GateStatus::Deferred), self::NATIVE);

        return $this->phase === Phase::Complete && $native !== []
            ? new CheckRow(
                'gate',
                CheckStatus::Failed,
                implode(', ', $native) . ' deferred, and complete needs the native gates: ' . $summary,
                self::ENVIRONMENT,
            )
            : new CheckRow('gate', CheckStatus::Deferred, $summary . ' A deferral is not a pass.', '');
    }
}
