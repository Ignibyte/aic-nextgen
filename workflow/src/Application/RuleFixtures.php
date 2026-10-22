<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\RuleCase;

/**
 * A house rule's probe on disk (#104): its `base/`, `held-*` and
 * `broken-*` overlays, laid out as files a rule can read.
 */
interface RuleFixtures
{
    /**
     * @param HouseRule $houseRule The rule.
     *
     * @return list<RuleCase>|null Its cases, each with its changes against the base; null when it ships no probe.
     */
    public function cases(HouseRule $houseRule): ?array;
}
