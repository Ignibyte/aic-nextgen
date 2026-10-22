<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\SeekerBrief;
use AIC\Workflow\Application\SeekerCheck;
use AIC\Workflow\Application\SeekerFiling;
use AIC\Workflow\Application\SeekerReach;
use AIC\Workflow\Application\SeekerRun;

/**
 * The seeker's composition (#103): its check at code (#127), the brief
 * behind `workflow:seeker-brief` with its one hop, and the filing behind
 * `workflow:seeker-report`, whose wiring is `SeekerFilingParts`.
 */
final readonly class SeekerParts
{
    /**
     * This package's root, where the checklist is.
     */
    private const string PACKAGE = __DIR__ . '/../..';

    /**
     * @param string        $root          The project's root.
     * @param EvidenceParts $evidenceParts The store, the subject and the host.
     */
    public function __construct(
        private string $root,
        private EvidenceParts $evidenceParts,
    ) {
    }

    /**
     * @return SeekerCheck The seeker's row at code.
     */
    public function seekerCheck(): SeekerCheck
    {
        return new SeekerCheck($this->seekerLog());
    }

    /**
     * @return SeekerFiling Files a ledger.
     */
    public function seekerFiling(): SeekerFiling
    {
        return (new SeekerFilingParts($this->root, $this->evidenceParts, $this))->seekerFiling();
    }

    /**
     * @return SeekerBrief The seeker's scope.
     */
    public function seekerBrief(): SeekerBrief
    {
        return new SeekerBrief(
            $this->seekerRun(),
            $this->evidenceParts->runSubject(),
            $this->evidenceParts->runHost(),
            $this->checklist(),
            $this->seekerReach(),
        );
    }

    /**
     * @return SeekerRun The open run.
     */
    public function seekerRun(): SeekerRun
    {
        return new SeekerRun(new RunStateFile($this->root));
    }

    /**
     * @return SeekerStore The filed ledgers.
     */
    public function seekerLog(): SeekerStore
    {
        return new SeekerStore($this->root);
    }

    /**
     * @return SeekerChecklistFile The AIC checklist.
     */
    public function checklist(): SeekerChecklistFile
    {
        return new SeekerChecklistFile($this->root, self::PACKAGE, (new ComposerManifest($this->root, self::PACKAGE))->vendorDir());
    }

    /**
     * @return SeekerReach The files one hop from the change.
     */
    private function seekerReach(): SeekerReach
    {
        $codeParts = new CodeParts($this->root);

        return new SeekerReach($codeParts->codeIndex(), $codeParts->codeLookup());
    }
}
