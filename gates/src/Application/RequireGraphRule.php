<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\BoundaryViolation;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTree;
use InvalidArgumentException;
use Override;

/**
 * R4 of gate:26 (#94): the modules' requires name modules that exist and form
 * no cycle. The build's own `ModuleGraph` decides, so gate:26 and
 * `aic-build` refuse the same graphs with the same words.
 */
final readonly class RequireGraphRule implements ModuleRule
{
    /**
     * @param ModuleGraph $moduleGraph Orders the modules, refusing a bad graph.
     */
    public function __construct(
        private ModuleGraph $moduleGraph = new ModuleGraph(),
    ) {
    }

    /**
     * @param ModuleTree $moduleTree The modules.
     *
     * @return list<BoundaryViolation> The graph's breach, if any: an unknown module or a cycle.
     */
    #[Override]
    public function check(ModuleTree $moduleTree): array
    {
        try {
            $this->moduleGraph->order($moduleTree->modules);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return [new BoundaryViolation('modules/', $invalidArgumentException->getMessage() . ' Remove or fix that require.')];
        }

        return [];
    }
}
