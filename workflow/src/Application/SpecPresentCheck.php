<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use Override;

/**
 * #100's one check: plan holds until its spec is written (Droost's
 * `awaitingSpecRow`). The spec is a regular file inside the project, at the
 * run's spec path, with text in it.
 */
final readonly class SpecPresentCheck implements PhaseCheck
{
    /**
     * @param SpecFile $specFile Reads the spec.
     */
    public function __construct(
        private SpecFile $specFile,
    ) {
    }

    /**
     * @return Phase Plan.
     */
    #[Override]
    public function phase(): Phase
    {
        return Phase::Plan;
    }

    /**
     * @param RunState $runState The run, at plan.
     *
     * @return CheckRow Passed when the spec is there with text; blocked, with the path to write, otherwise.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $specPath = $runState->runStart->specPath;

        if ($this->specFile->text($specPath) !== null) {
            return new CheckRow('spec', CheckStatus::Passed, $specPath . ' is written.', '');
        }

        return new CheckRow(
            'spec',
            CheckStatus::Blocked,
            'plan holds until its spec is written.',
            "write the plan's spec to " . $specPath . ', then run bin/aic workflow:run again.',
        );
    }
}
