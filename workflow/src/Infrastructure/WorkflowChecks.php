<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\BrowserCheck;
use AIC\Workflow\Application\CompositionCheck;
use AIC\Workflow\Application\ContractCheck;
use AIC\Workflow\Application\CriteriaCheck;
use AIC\Workflow\Application\DeclarationCheck;
use AIC\Workflow\Application\FreezeCheck;
use AIC\Workflow\Application\PhaseCheck;
use AIC\Workflow\Application\RenderedCheck;
use AIC\Workflow\Application\SpecFreeze;
use AIC\Workflow\Application\SpecPresentCheck;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\Phase;

use function array_key_exists;

/**
 * The engine's checks, in the order they run at each phase (#100, #101).
 * Plan: the spec is written, then it reads as a contract and is frozen.
 * Code, check and complete: the gate, the freeze, the declaration audit and
 * the composition check; check and complete add the rendered check; check
 * adds the browser evidence, complete the criteria; code and complete end
 * with the seeker's inspection (#103).
 */
final readonly class WorkflowChecks
{
    /**
     * @param string                    $root          The project's root.
     * @param EvidenceParts             $evidenceParts The store, the subject and the gate.
     * @param string                    $site          The rendered check's site.
     * @param RuleParts                 $ruleParts     The house rules' checks (#104).
     * @param array<string, PhaseCheck> $seekerChecks  The seeker's row at code and at complete, by phase (#103).
     */
    public function __construct(
        private string $root,
        private EvidenceParts $evidenceParts,
        private string $site,
        private RuleParts $ruleParts,
        private array $seekerChecks,
    ) {
    }

    /**
     * @param UtcTime $utcTime The clock.
     *
     * @return list<PhaseCheck> Every check, in order.
     */
    public function all(UtcTime $utcTime): array
    {
        $parts = $this->evidenceParts;
        $specFile = $parts->specFile();
        $checks = [
            new SpecPresentCheck($specFile),
            new ContractCheck($specFile, $parts->evidenceLog(), $parts->gateExecutor(), $utcTime),
            ...$this->ruleParts->plan(),
        ];

        foreach ([Phase::Code, Phase::Check, Phase::Complete] as $phase) {
            $checks = [...$checks, ...$this->exit($phase)];
        }

        $checks[] = new BrowserCheck($specFile, $parts->evidenceLog(), $parts->evidenceRecorder(), $parts->runSubject());
        $checks[] = new CriteriaCheck($specFile, $parts->evidenceLog(), $parts->evidenceRecorder(), $parts->runSubject());

        return [...$checks, ...$this->seeker(Phase::Complete)];
    }

    /**
     * @param Phase $phase Code, check or complete.
     *
     * @return list<PhaseCheck> The checks every exit after plan runs, in order.
     */
    private function exit(Phase $phase): array
    {
        $parts = $this->evidenceParts;
        $specFile = $parts->specFile();
        $checks = [
            $parts->gateCheck($phase),
            new FreezeCheck($phase, $specFile, new SpecFreeze(), $parts->evidenceLog(), $parts->evidenceRecorder(), $parts->runSubject()),
            new DeclarationCheck($phase, $parts->runHost(), $specFile, $parts->evidenceRecorder(), $parts->runSubject()),
            $this->composition($phase),
            ...$this->ruleParts->exit($phase),
        ];
        $renderedCheck = new RenderedCheck(
            $phase,
            $this->site,
            new HttpSiteProbe(),
            $specFile,
            $parts->evidenceRecorder(),
            $parts->runSubject(),
        );

        return $phase === Phase::Code
            ? [...$checks, ...$this->seeker(Phase::Code)]
            : [...$checks, $renderedCheck];
    }

    /**
     * @param Phase $phase Code, check or complete.
     *
     * @return CompositionCheck The composition check at it.
     */
    private function composition(Phase $phase): CompositionCheck
    {
        $parts = $this->evidenceParts;
        $surfaceEntries = new SurfaceEntries($this->root, $parts->runHost());

        return new CompositionCheck(
            $phase,
            $surfaceEntries,
            $parts->specFile(),
            $parts->evidenceLog(),
            $parts->evidenceRecorder(),
            $parts->runSubject(),
        );
    }

    /**
     * @param Phase $phase Code or complete.
     *
     * @return list<PhaseCheck> The seeker's row at it; none at another phase (#103).
     */
    private function seeker(Phase $phase): array
    {
        return array_key_exists($phase->value, $this->seekerChecks)
            ? [$this->seekerChecks[$phase->value]]
            : [];
    }
}
