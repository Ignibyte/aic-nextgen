<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function hash;

/**
 * What the agent could have changed since a report (#117): the run's subject
 * (the changed files, `composer.lock`, the toolchain) and the spec, which
 * lives under the ignored `var/` and so is not in the subject. Equal keys
 * mean nothing moved; a confirmation is bound to the key it was asked about.
 */
final readonly class ProgressKey
{
    /**
     * @param RunSubject $runSubject The tree's subject hash.
     * @param SpecFile   $specFile   The run's spec.
     */
    public function __construct(
        private RunSubject $runSubject,
        private SpecFile $specFile,
    ) {
    }

    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return string An xxh128 over the subject and the spec's sha256 (empty spec when it is missing).
     */
    public function key(RunState $runState): string
    {
        $spec = $this->specFile->text($runState->runStart->specPath) ?? '';

        return hash('xxh128', $this->runSubject->hash($runState) . "\0" . hash('sha256', $spec));
    }
}
