<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\FrozenSpec;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_slice;
use function count;
use function implode;

/**
 * The spec freeze at every exit after plan (#101): the criteria, the file
 * manifest, the entry points, the decisions and the browser items are
 * append-only. A breach quotes the frozen rows it lost and holds the phase,
 * where Droost only recorded it; a manifest row added since is recorded as
 * declared late.
 */
final readonly class FreezeCheck implements PhaseCheck
{
    /**
     * What to do about a breach.
     */
    private const string REMEDY = 'put the frozen rows back as they were; add rows below them instead of rewriting them.';

    /**
     * @param Phase            $phase            The phase it runs at.
     * @param SpecFile         $specFile         Reads the spec now.
     * @param SpecFreeze       $specFreeze       Compares it with the frozen one.
     * @param EvidenceLog      $evidenceLog      The freeze.
     * @param EvidenceRecorder $evidenceRecorder Records the verdict.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private Phase $phase,
        private SpecFile $specFile,
        private SpecFreeze $specFreeze,
        private EvidenceLog $evidenceLog,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
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
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return CheckRow Passed, noting late rows; failed on a breach, a missing freeze or a missing spec.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        try {
            $frozenSpec = $this->evidenceLog->frozen($runState->runStart->runId);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->evidenceRecorder->unrecorded('freeze', $workflowRefusal);
        }

        $text = $this->specFile->text($runState->runStart->specPath);
        [$failures, $passed] = $frozenSpec instanceof FrozenSpec && $text !== null
            ? $this->compare($frozenSpec, $text)
            : [[$text === null ? 'the spec is gone.' : 'planning froze no spec.'], ''];
        $findings = new Findings('freeze', $failures, $passed, $this->runSubject->hash($runState));

        return $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), self::REMEDY);
    }

    /**
     * @param FrozenSpec $frozenSpec What planning ended on.
     * @param string     $text       The spec now.
     *
     * @return array{list<string>, string} The breaches, and why it passed when there are none.
     */
    private function compare(FrozenSpec $frozenSpec, string $text): array
    {
        $specContract = $this->specFile->contract($frozenSpec->text);
        $now = $this->specFile->contract($text);
        $breaches = $this->specFreeze->breaches($specContract, $now);
        $late = $this->specFreeze->late($specContract, $now);
        $lost = 'the spec lost ' . count($breaches) . ' frozen row(s): ' . implode('; ', array_slice($breaches, 0, 10));

        return [
            $breaches === [] ? [] : [$lost],
            $late === [] ? 'the frozen sections hold.' : 'the frozen sections hold; declared late: ' . implode(', ', $late) . '.',
        ];
    }
}
