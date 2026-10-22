<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\ConfirmCheck;
use AIC\Workflow\Application\Guard\OperatorHold;
use AIC\Workflow\Application\PhaseCheck;
use AIC\Workflow\Application\ProgressKey;
use AIC\Workflow\Application\QuestionHold;
use AIC\Workflow\Application\RunJournal;
use AIC\Workflow\Application\StalledRows;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\Phase;

/**
 * The holds' parts (#102, #117), kept apart so `WorkflowParts` stays under
 * PHPMD's coupling cap: the operator hold, the stall rule, the questions a
 * run waits on and the phase-exit confirmation at every phase.
 */
final readonly class HoldParts
{
    /**
     * @param string        $root          The project's root.
     * @param EvidenceParts $evidenceParts The subject, the spec and the host.
     * @param RunStateFile  $runStateFile  The run's store.
     * @param UtcTime       $utcTime       The clock.
     */
    public function __construct(
        private string $root,
        private EvidenceParts $evidenceParts,
        private RunStateFile $runStateFile,
        private UtcTime $utcTime,
    ) {
    }

    /**
     * @param RunJournal $runJournal The events the hold and the resume log.
     *
     * @return OperatorHold The operator hold (#102).
     */
    public function operatorHold(RunJournal $runJournal): OperatorHold
    {
        return new OperatorHold($this->runStateFile, $runJournal, $this->evidenceParts->runHost(), $this->utcTime);
    }

    /**
     * @return StalledRows The stall rule (#117).
     */
    public function stalledRows(): StalledRows
    {
        return new StalledRows($this->progressKey());
    }

    /**
     * @return QuestionHold The questions a run waits on (#117).
     */
    public function questionHold(): QuestionHold
    {
        $localHost = $this->evidenceParts->runHost();

        return new QuestionHold($this->runStateFile, new EventLogFile($this->root, $this->utcTime, $localHost), $localHost, $this->utcTime);
    }

    /**
     * @return list<PhaseCheck> The phase-exit confirmation, one per phase, each registered last (#117).
     */
    public function confirmChecks(): array
    {
        $checks = [];

        foreach ([Phase::Plan, Phase::Code, Phase::Check, Phase::Complete] as $phase) {
            $checks[] = new ConfirmCheck($phase, $this->progressKey());
        }

        return $checks;
    }

    /**
     * @return ProgressKey What the agent could have changed since a report.
     */
    private function progressKey(): ProgressKey
    {
        return new ProgressKey($this->evidenceParts->runSubject(), $this->evidenceParts->specFile());
    }
}
