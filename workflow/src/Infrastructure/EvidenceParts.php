<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\EvidenceRecorder;
use AIC\Workflow\Application\GateCheck;
use AIC\Workflow\Application\GateRunner;
use AIC\Workflow\Application\GateSummary;
use AIC\Workflow\Application\RunSubject;
use AIC\Workflow\Application\SpecFile;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\WorkflowSettings;

use function rtrim;

/**
 * The services #101's checks share, built for one project (#101): the
 * evidence store, the subject, the spec file, the host and the gate.
 */
final readonly class EvidenceParts
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
     * @return EvidenceStore The store.
     */
    public function evidenceLog(): EvidenceStore
    {
        return new EvidenceStore($this->root);
    }

    /**
     * @return EvidenceRecorder Records verdicts into it.
     */
    public function evidenceRecorder(): EvidenceRecorder
    {
        return new EvidenceRecorder($this->evidenceLog());
    }

    /**
     * @return LocalHost The machine.
     */
    public function runHost(): LocalHost
    {
        return new LocalHost($this->root);
    }

    /**
     * @return SpecFile The spec.
     */
    public function specFile(): SpecFile
    {
        return new SpecFile($this->root);
    }

    /**
     * @return RunSubject The subject; the ticket directory is the engine's own write.
     */
    public function runSubject(): RunSubject
    {
        return new RunSubject(
            $this->runHost(),
            new SubjectHasher($this->root),
            $this->workflowSettings->toolchain,
            [rtrim($this->workflowSettings->tickets, '/') . '/'],
        );
    }

    /**
     * @return ComposerGateExecutor The gate suite.
     */
    public function gateExecutor(): ComposerGateExecutor
    {
        return new ComposerGateExecutor($this->root, new LocalProcess($this->root), $this->workflowSettings->gateTimeout);
    }

    /**
     * @param Phase $phase Code, check or complete.
     *
     * @return GateCheck The gate at that phase.
     */
    public function gateCheck(Phase $phase): GateCheck
    {
        $gateExecutor = $this->gateExecutor();

        return new GateCheck(
            $phase,
            new GateRunner($gateExecutor, new GateSummary(), $this->evidenceLog()),
            $gateExecutor,
            $this->evidenceLog(),
            $this->evidenceRecorder(),
            $this->runSubject(),
            $this->workflowSettings->maxGateRetries,
        );
    }
}
