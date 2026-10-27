<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\BrowserCheck;
use AIC\Workflow\Domain\Phase;

/**
 * The browser rows' wiring (#174): at check the engine runs each look with
 * the project's `vendor/bin/aic-gate browser` against the site, and at complete
 * it reads each look's keep or drop.
 */
final readonly class BrowserParts
{
    /**
     * @param string        $root          The project's root.
     * @param EvidenceParts $evidenceParts The spec, the recorder and the subject hash.
     * @param string        $site          The app's URL from config/workflow.php; empty when none is set.
     */
    public function __construct(
        private string $root,
        private EvidenceParts $evidenceParts,
        private string $site,
    ) {
    }

    /**
     * @return list<BrowserCheck> The browser row at check, then at complete.
     */
    public function checks(): array
    {
        $checks = [];

        foreach ([Phase::Check, Phase::Complete] as $phase) {
            $checks[] = new BrowserCheck(
                $phase,
                $this->site,
                $this->root . '/vendor/bin/aic-gate',
                new LocalProcess($this->root),
                new LocalProjectFiles($this->root),
                $this->evidenceParts->specFile(),
                $this->evidenceParts->evidenceRecorder(),
                $this->evidenceParts->runSubject(),
            );
        }

        return $checks;
    }
}
