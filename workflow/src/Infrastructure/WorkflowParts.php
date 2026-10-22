<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Guard\OperatorHold;
use AIC\Workflow\Application\RunBegin;
use AIC\Workflow\Application\RunEngine;
use AIC\Workflow\Application\RunJournal;
use AIC\Workflow\Application\RunReset;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\WorkflowSettings;

/**
 * The workflow's composition (#100), the one place that builds its services,
 * for an installed project: its settings read, its root known.
 */
final readonly class WorkflowParts
{
    /**
     * @param string           $root             The project's root.
     * @param WorkflowSettings $workflowSettings Its settings.
     */
    public function __construct(
        private string $root,
        private WorkflowSettings $workflowSettings,
    ) {
    }

    /**
     * @return RunEngine The engine, with its checks (#100, #101).
     */
    public function engine(): RunEngine
    {
        $utcTime = new UtcTime(new SystemClock());

        $localHost = $this->evidenceParts()->runHost();
        $runJournal = $this->journal($utcTime);
        $evidenceParts = $this->evidenceParts();

        return new RunEngine(
            $this->runStore(),
            new RunBegin(
                $this->runStore(),
                $this->tickets($utcTime),
                $runJournal,
                $localHost,
                $utcTime,
                $this->workflowSettings->enforcement,
                $this->ruleParts($evidenceParts)->ruleFiles(),
            ),
            $runJournal,
            $this->checks()->all($utcTime),
            new OperatorHold($this->runStore(), $runJournal, $localHost, $utcTime),
            $evidenceParts->toolCallIngest(),
        );
    }

    /**
     * @return RunReset The operator's reset.
     */
    public function runReset(): RunReset
    {
        return new RunReset($this->runStore(), $this->journal(new UtcTime(new SystemClock())), $this->evidenceParts()->runHost());
    }

    /**
     * @return RunStateFile The run's store.
     */
    public function runStore(): RunStateFile
    {
        return new RunStateFile($this->root);
    }

    /**
     * @return SeekerParts The seeker's parts (#103).
     */
    public function seekerParts(): SeekerParts
    {
        return new SeekerParts($this->root, $this->evidenceParts());
    }

    /**
     * @return EvidenceParts The services the checks share.
     */
    private function evidenceParts(): EvidenceParts
    {
        return new EvidenceParts($this->root, $this->workflowSettings);
    }

    /**
     * @return WorkflowChecks The engine's checks for this project.
     */
    private function checks(): WorkflowChecks
    {
        $evidenceParts = $this->evidenceParts();
        $seekerParts = $this->seekerParts();

        return new WorkflowChecks(
            $this->root,
            $evidenceParts,
            $this->workflowSettings->site,
            $this->ruleParts($evidenceParts),
            ['code' => $seekerParts->atCode(), 'complete' => $seekerParts->atComplete()],
        );
    }

    /**
     * @param EvidenceParts $evidenceParts The store, the subject and the host.
     *
     * @return RuleParts The house rules' parts (#104).
     */
    private function ruleParts(EvidenceParts $evidenceParts): RuleParts
    {
        return new RuleParts($this->root, $this->workflowSettings, $evidenceParts);
    }

    /**
     * @param UtcTime $utcTime The clock.
     *
     * @return RunJournal The events and the ticket's moves.
     */
    private function journal(UtcTime $utcTime): RunJournal
    {
        $eventLogFile = new EventLogFile($this->root, $utcTime, $this->evidenceParts()->runHost());

        return new RunJournal($eventLogFile, $this->tickets($utcTime), $utcTime);
    }

    /**
     * @param UtcTime $utcTime The clock.
     *
     * @return MarkdownTickets The project's tickets.
     */
    private function tickets(UtcTime $utcTime): MarkdownTickets
    {
        return new MarkdownTickets($this->root, $this->workflowSettings->tickets, $utcTime);
    }
}
