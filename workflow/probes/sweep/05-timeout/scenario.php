<?php

declare(strict_types=1);

/*
 * 05-timeout (#119; #101 AC 8): with gateTimeout 5, the gate run is stopped
 * before its verdict (exit 124), the run is held, and no gate runner is
 * left behind.
 */

use AIC\Workflow\Domain\Probe\CommandStep;
use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\RunStep;

$probePlan = new ProbePlan();

return new ProbeScenario([
    ...$probePlan->toCode(),
    $probePlan->label(),
    new RunStep('held', [new Expect('gate', 'held', 'the gate run passed its timeout and was stopped (exit 124)')]),
    new CommandStep(['pgrep', '-f', '[g]ate-runner\\.sh'], 1),
]);
