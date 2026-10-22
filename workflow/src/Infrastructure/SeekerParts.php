<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\SeekerBrief;
use AIC\Workflow\Application\SeekerCheck;
use AIC\Workflow\Application\SeekerFiling;
use AIC\Workflow\Application\SeekerRun;
use AIC\Workflow\Domain\Phase;

/**
 * The seeker's composition (#103): its check at code and at complete, the
 * brief behind `workflow:seeker-brief`, and the filing behind
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
    public function atCode(): SeekerCheck
    {
        return new SeekerCheck(Phase::Code, $this->seekerLog(), $this->evidenceParts->runSubject());
    }

    /**
     * @return SeekerCheck The seeker's row at complete.
     */
    public function atComplete(): SeekerCheck
    {
        return new SeekerCheck(Phase::Complete, $this->seekerLog(), $this->evidenceParts->runSubject());
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
            $this->seekerLog(),
            $this->checklist(),
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
}
