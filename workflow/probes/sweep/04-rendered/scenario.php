<?php

declare(strict_types=1);

/*
 * 04-rendered (#119; #101 AC 9): the spec declares GET /notes answering 200,
 * and the app is served with its database out of reach, so /notes answers
 * 500. Code advances; check's rendered row names the route and its answer.
 */

use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\ServeStep;
use AIC\Workflow\Domain\Probe\StopStep;

$probePlan = new ProbePlan();

return new ProbeScenario([
    ...$probePlan->toCode(),
    $probePlan->label(),
    ...$probePlan->inspected(),
    new RunStep('advanced', [new Expect('gate', 'passed')]),
    new ServeStep(['PGPORT' => '1']),
    new RunStep('blocked', [new Expect('rendered', 'failed', 'GET /notes')]),
    new StopStep(),
], database: true);
