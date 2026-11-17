<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Application\EvidenceRecorder;
use AIC\Workflow\Application\PhaseCheck;
use AIC\Workflow\Application\RunSubject;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_any;
use function array_keys;
use function array_unique;
use function implode;
use function str_starts_with;

/**
 * The app's scaffolds' freeze (#299), the `scaffolds` row at code, check and
 * complete: every file under `scaffolds/` hashes as it did when the last run
 * completed, but the folders of the kinds this run's operator approved. A
 * change holds the run, whether made in this run or between runs; the
 * operator approves a scaffold run for the kind, or the bytes are restored.
 * When complete's row passes, it freezes the scaffolds as they are. Nothing
 * is frozen before the first run completes, so the row passes and says so.
 */
final readonly class ScaffoldFreezeCheck implements PhaseCheck
{
    /**
     * What to do about a changed scaffold.
     */
    private const string REMEDY = 'restore each file as it was frozen, or ask with bin/aic workflow:ask and have the operator answer '
        . '"scaffold <kind>".';

    /**
     * @param Phase             $phase             The phase it runs at.
     * @param ScaffoldLock      $scaffoldLock      The scaffolds now and as frozen.
     * @param EvidenceRecorder  $evidenceRecorder  Records the verdict.
     * @param RunSubject        $runSubject        The tree's subject hash.
     * @param ScaffoldApprovals $scaffoldApprovals The kinds the run may change.
     */
    public function __construct(
        private Phase $phase,
        private ScaffoldLock $scaffoldLock,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
        private ScaffoldApprovals $scaffoldApprovals = new ScaffoldApprovals(),
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
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When the record cannot be read or written, or the verdict recorded.
     *
     * @return CheckRow Passed when every unapproved scaffold file is as frozen; failed, naming each that is not.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $current = $this->scaffoldLock->current();
        $frozen = $this->scaffoldLock->frozen();
        $changed = $frozen === null
            ? []
            : $this->changed($frozen, $current, $this->scaffoldApprovals->kinds($runState));
        $failures = $changed === []
            ? []
            : ['changed since they were frozen, with no approval: ' . implode(', ', $changed) . '.'];
        $passed = $frozen === null
            ? 'no scaffold is frozen yet; complete freezes them.'
            : 'the scaffolds are as frozen, but the approved kinds.';

        if ($failures === [] && $this->phase === Phase::Complete) {
            $this->scaffoldLock->freeze($current);
        }

        $findings = new Findings('scaffolds', $failures, $passed, $this->runSubject->hash($runState));

        return $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), self::REMEDY);
    }

    /**
     * @param array<string, string> $frozen   The files as frozen.
     * @param array<string, string> $current  The files now.
     * @param list<string>          $approved The kinds the run may change.
     *
     * @return list<string> Each path added, removed or changed outside an approved kind's folder.
     */
    private function changed(array $frozen, array $current, array $approved): array
    {
        $changed = [];

        foreach (array_unique([...array_keys($frozen), ...array_keys($current)]) as $path) {
            $open = array_any($approved, static fn (string $kind): bool => str_starts_with($path, 'scaffolds/' . $kind . '/'));

            if ($open || ($frozen[$path] ?? null) === ($current[$path] ?? null)) {
                continue;
            }

            $changed[] = $path;
        }

        return $changed;
    }
}
