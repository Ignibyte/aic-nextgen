<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function preg_match;

/**
 * Criteria verification at complete (#101, Droost's `CriteriaVerification`
 * without tests): each criterion's `Met` cell is ticked and cites a check
 * the store holds green and fresh on the tree as it is: `gate`, `gate:N`,
 * `composition`, `declaration`, `freeze`, `rendered GET /path`, `browser`,
 * `rules` or `rule:<id>` (#104). A tick that cites nothing is the agent's word, and it holds.
 */
final readonly class CriteriaCheck implements PhaseCheck
{
    use RegexCaptures;

    /**
     * What a `Met` cell may cite.
     */
    private const string CITATION = '/gate:\\d+|rule:[a-z0-9-]+|rendered GET [^\\s,;]+'
        . '|\\b(?:gate|composition|declaration|freeze|browser|rules)\\b/';

    /**
     * What to do about a criterion not shown.
     */
    private const string REMEDY = "tick each criterion's Met cell with the check that shows it, such as \"[x] gate:21\", once it is green.";

    /**
     * @param SpecFile         $specFile         The criteria.
     * @param EvidenceLog      $evidenceLog      The verdicts.
     * @param EvidenceRecorder $evidenceRecorder Records this one.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private SpecFile $specFile,
        private EvidenceLog $evidenceLog,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
    ) {
    }

    /**
     * @return Phase Complete.
     */
    #[Override]
    public function phase(): Phase
    {
        return Phase::Complete;
    }

    /**
     * @param RunState $runState The run, at complete.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return CheckRow Passed when every criterion cites a fresh green check; failed, naming each that does not.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $subjectHash = $this->runSubject->hash($runState);
        $text = $this->specFile->text($runState->runStart->specPath);

        try {
            $criteria = $text === null
                ? []
                : $this->specFile->contract($text)->criteria();
            $failures = $this->failures($criteria, $runState->runStart->runId, $subjectHash);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->evidenceRecorder->unrecorded('criteria', $workflowRefusal);
        }

        $findings = new Findings('criteria', $failures, 'every criterion cites a fresh green check.', $subjectHash);

        return $this->evidenceRecorder->row($runState, Phase::Complete, $findings->record(), self::REMEDY);
    }

    /**
     * @param list<array{string, string}> $criteria    Each criterion's number and `Met` cell.
     * @param string                      $runId       The run.
     * @param string                      $subjectHash The tree.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return list<string> What keeps each from being shown.
     */
    private function failures(array $criteria, string $runId, string $subjectHash): array
    {
        $failures = [];

        foreach ($criteria as [$number, $met]) {
            $failures = [...$failures, ...$this->criterion($number, $met, $runId, $subjectHash)];
        }

        return $failures;
    }

    /**
     * @param string $number      The criterion's number.
     * @param string $met         Its `Met` cell.
     * @param string $runId       The run.
     * @param string $subjectHash The tree.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return list<string> What keeps it from being shown; empty when it is.
     */
    private function criterion(string $number, string $met, string $runId, string $subjectHash): array
    {
        $ticked = preg_match('/\\[[xX]\\]/', $met) === 1;
        $citations = $this->citations($met);

        if ($ticked && $citations !== []) {
            return $this->unproven($number, $citations, $runId, $subjectHash);
        }

        return ['criterion ' . $number . ($ticked ? ' is ticked and cites no check.' : ' is not met.')];
    }

    /**
     * @param string       $number      The criterion's number.
     * @param list<string> $citations   The checks it cites.
     * @param string       $runId       The run.
     * @param string       $subjectHash The tree.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return list<string> Each cited check that is not green and fresh.
     */
    private function unproven(string $number, array $citations, string $runId, string $subjectHash): array
    {
        $failures = [];

        foreach ($citations as $citation) {
            if ($this->green($runId, $citation, $subjectHash)) {
                continue;
            }

            $failures[] = 'criterion ' . $number . ' cites ' . $citation . ', which is not green and fresh.';
        }

        return $failures;
    }

    /**
     * @param string $runId       The run.
     * @param string $citation    A check a criterion cites.
     * @param string $subjectHash The tree.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return bool Whether its newest verdict is green on that tree.
     */
    private function green(string $runId, string $citation, string $subjectHash): bool
    {
        $checkRecord = $this->evidenceLog->latest($runId, $citation);

        return $checkRecord instanceof CheckRecord && $checkRecord->greenFor($subjectHash);
    }

    /**
     * @param string $met A `Met` cell.
     *
     * @return list<string> The checks it cites, in order.
     */
    private function citations(string $met): array
    {
        return self::regexColumn(self::CITATION, $met, 0);
    }
}
