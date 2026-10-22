<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\ProcessResult;

/**
 * Runs a house rule's file (#104): `PHP_BINARY <file>` in the project, its
 * input on stdin, stopped after its timeout.
 */
interface RuleExecutor
{
    /**
     * @param HouseRule $houseRule The rule.
     * @param string    $input     Its input, an `aic.house-rule/1` JSON object.
     *
     * @return ProcessResult Its exit code (124 when stopped, 127 when its file is not there) and its output.
     */
    public function run(HouseRule $houseRule, string $input): ProcessResult;
}
