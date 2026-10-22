<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\QuestionAnswer;
use AIC\Workflow\Domain\RunState;
use Override;

use function array_any;

/**
 * The phase exit's confirmation (#117), registered last at every phase. An
 * interactive run, and an agentic one at complete when the operator confirms
 * complete, passes only when the operator answered `advance` for this phase
 * on the tree as it stands; otherwise the row asks, and a report held only
 * by it pauses the run for the operator.
 */
final readonly class ConfirmCheck implements PhaseCheck
{
    /**
     * How the operator answers.
     */
    private const string REMEDY = 'the operator answers from a terminal: bin/aic workflow:answer advance|stay "<note>".';

    /**
     * @param Phase       $phase       The phase it confirms.
     * @param ProgressKey $progressKey The tree's key, which an answer is bound to.
     */
    public function __construct(
        private Phase $phase,
        private ProgressKey $progressKey,
    ) {
    }

    /**
     * @return Phase The phase it confirms.
     */
    #[Override]
    public function phase(): Phase
    {
        return $this->phase;
    }

    /**
     * @param RunState $runState The run at the phase.
     *
     * @return CheckRow Passed when no confirmation is due or it was given on this tree; asked otherwise.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $runStart = $runState->runStart;
        $due = $runStart->mode->confirmsExits() || ($this->phase === Phase::Complete && $runStart->humanComplete);

        if (!$due) {
            return new CheckRow('confirm', CheckStatus::Passed, 'no confirmation is due in an agentic run.', '');
        }

        $key = $this->progressKey->key($runState);
        $given = array_any(
            $runState->qaHistory,
            fn (QuestionAnswer $questionAnswer): bool => $questionAnswer->phase === $this->phase->value
                && $questionAnswer->option === 'advance'
                && $questionAnswer->key === $key,
        );

        return $given
            ? new CheckRow('confirm', CheckStatus::Passed, 'the operator confirmed this exit on this tree.', '')
            : new CheckRow('confirm', CheckStatus::Asked, $this->question(), self::REMEDY);
    }

    /**
     * @return string What the operator is asked at this phase's exit.
     */
    private function question(): string
    {
        return match ($this->phase) {
            Phase::Plan => 'Advance from plan to code with the spec as it stands?',
            Phase::Code => 'Advance from code to check with the tree as it stands?',
            Phase::Check => 'Advance from check to complete with the tree as it stands?',
            Phase::Complete => 'Finish the run with the tree as it stands?',
        };
    }
}
