<?php

declare(strict_types=1);

/*
 * 09-grounding (#119, #118): plan exit with no lookups and a modify row for
 * a file the run's base commit does not hold is blocked with both reasons; after the lookups
 * and the row's fix, plan advances.
 */

use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\LookStep;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

return new ProbeScenario([
    new RunStep('blocked', ticket: 1),
    new WriteStep('var/workflow/spec-1.md', 'spec.md.fixture'),
    new RunStep('blocked', [
        new Expect('grounding', 'failed', 'plan has not looked yet (0 lookups so far)'),
        new Expect('grounding', 'failed', 'row modules/site/src/Controller/NoSuchPage.php (modify) is not a file the run starts from'),
    ]),
    new ReplaceStep(
        'var/workflow/spec-1.md',
        "| `modules/site/src/Controller/NoSuchPage.php` | modify | A row for a file git does not list |\n",
        '',
    ),
    new LookStep(),
    new RunStep('advanced', [new Expect('grounding', 'passed')]),
]);
