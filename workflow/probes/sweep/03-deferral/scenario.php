<?php

declare(strict_types=1);

/*
 * 03-deferral (#119; #101 AC 7): code passes with the database, then the
 * changelog is edited and the rest runs with it out of reach (PGPORT=1), so
 * gate:23 defers. Check advances on the deferral; complete is held for the
 * operator, as a deferral is no pass and complete needs the native gates.
 */

use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\RunStep;

$probePlan = new ProbePlan();
$away = ['PGPORT' => '1'];

return new ProbeScenario([
    ...$probePlan->toCode(),
    $probePlan->label(),
    ...$probePlan->inspected(),
    new RunStep('advanced'),
    new ReplaceStep(
        'CHANGELOG.md',
        "## Unreleased\n",
        "## Unreleased\n\n- The home page's uploads link says each file is checked by its content.\n",
    ),
    new RunStep('advanced', [new Expect('gate', 'deferred', 'gate:23')], $away),
    new ReplaceStep('var/workflow/spec-1.md', 'content | [ ] |', 'content | [x] gate:6 |'),
    new RunStep('held', [new Expect('gate', 'held', 'gate:23 deferred, and complete needs the native gates')], $away),
], database: true);
