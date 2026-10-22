<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use Override;

use function file_get_contents;
use function is_file;
use function is_link;
use function realpath;
use function str_starts_with;
use function trim;

/**
 * #100's one check: plan holds until its spec is written (Droost's
 * `awaitingSpecRow`). The spec is a regular file inside the project, at the
 * run's spec path, with text in it.
 */
final readonly class SpecPresentCheck implements PhaseCheck
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
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

        if ($this->written($this->root . '/' . $specPath)) {
            return new CheckRow('spec', CheckStatus::Passed, $specPath . ' is written.', '');
        }

        return new CheckRow(
            'spec',
            CheckStatus::Blocked,
            'plan holds until its spec is written.',
            "write the plan's spec to " . $specPath . ', then run bin/aic workflow:run again.',
        );
    }

    /**
     * @param string $path The spec's path.
     *
     * @return bool Whether it is a regular file, no symlink, inside the project, with text in it.
     */
    private function written(string $path): bool
    {
        $real = realpath($path);
        $root = realpath($this->root);

        if (
            !is_file($path)
            || is_link($path)
            || $real === false
            || $root === false
            || !str_starts_with($real, $root . '/')
        ) {
            return false;
        }

        return trim((string) file_get_contents($path)) !== '';
    }
}
