<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\ContractCheck;
use AIC\Workflow\Application\GroundingCheck;
use AIC\Workflow\Application\GroundingLooks;
use AIC\Workflow\Application\GroundingRules;
use AIC\Workflow\Application\GroundingSpec;
use AIC\Workflow\Application\PhaseCheck;
use AIC\Workflow\Application\SpecPresentCheck;
use AIC\Workflow\Application\UtcTime;

/**
 * Plan's own checks, composed (#100, #101, #118), apart from
 * `WorkflowChecks` so it stays under PHPMD's coupling cap: the spec is
 * there, it reads as a contract, and it is grounded in the code, over a code
 * index rebuilt each attempt and the run's recorded lookups.
 */
final readonly class PlanParts
{
    /**
     * @param string        $root          The project's root.
     * @param EvidenceParts $evidenceParts The spec, the store, the subject and the gates.
     */
    public function __construct(
        private string $root,
        private EvidenceParts $evidenceParts,
    ) {
    }

    /**
     * @param UtcTime $utcTime The clock.
     *
     * @return list<PhaseCheck> The spec's presence, its contract, then its grounding.
     */
    public function checks(UtcTime $utcTime): array
    {
        $parts = $this->evidenceParts;
        $specFile = $parts->specFile();

        return [
            new SpecPresentCheck($specFile),
            new ContractCheck($specFile, $parts->evidenceLog(), $parts->gateExecutor(), $utcTime),
            $this->groundingCheck(),
        ];
    }

    /**
     * @return GroundingCheck Plan's grounding (#118).
     */
    private function groundingCheck(): GroundingCheck
    {
        $parts = $this->evidenceParts;
        $codeParts = new CodeParts($this->root);
        $codeLookup = $codeParts->codeLookup();
        $codePsr4 = new CodePsr4($this->root);
        $groundingRules = new GroundingRules($codeLookup, $codePsr4, $parts->runHost());
        $groundingLooks = new GroundingLooks($codeLookup, $codePsr4, $groundingRules);
        $groundingSpec = new GroundingSpec($parts->specFile(), $groundingRules, $groundingLooks);
        $codeQueryLog = new CodeQueryLog($this->root);

        return new GroundingCheck($groundingSpec, $codeParts->codeIndex(), $codeQueryLog, $parts->evidenceRecorder(), $parts->runSubject());
    }
}
