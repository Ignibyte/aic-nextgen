<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\SeekerFiling;
use AIC\Workflow\Application\SeekerJournal;
use AIC\Workflow\Application\SeekerLedgerParser;
use AIC\Workflow\Application\SeekerTree;
use AIC\Workflow\Application\SeekerVerifier;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Infrastructure\Guard\GuardLedgerFile;

/**
 * The wiring behind `workflow:seeker-report` (#103): the parser, the tree
 * check, the guard's record of returns, the store and the event log.
 */
final readonly class SeekerFilingParts
{
    /**
     * @param string        $root          The project's root.
     * @param EvidenceParts $evidenceParts The store, the subject and the host.
     * @param SeekerParts   $seekerParts   The run, the store and the checklist.
     */
    public function __construct(
        private string $root,
        private EvidenceParts $evidenceParts,
        private SeekerParts $seekerParts,
    ) {
    }

    /**
     * @return SeekerFiling Files a ledger.
     */
    public function seekerFiling(): SeekerFiling
    {
        $utcTime = new UtcTime(new SystemClock());
        $seekerLog = $this->seekerParts->seekerLog();
        $runHost = $this->evidenceParts->runHost();

        return new SeekerFiling(
            $this->seekerParts->seekerRun(),
            $runHost,
            new SeekerLedgerParser($this->seekerParts->checklist()),
            new SeekerTree($this->evidenceParts->runSubject(), $this->evidenceParts->evidenceLog()),
            new SeekerVerifier(new SeekerGuardReturns($this->root, new GuardLedgerFile($this->root)), $seekerLog),
            $seekerLog,
            new SeekerJournal(new EventLogFile($this->root, $utcTime, $runHost)),
            $utcTime,
        );
    }
}
