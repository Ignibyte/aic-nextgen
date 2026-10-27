<?php

declare(strict_types=1);

/*
 * 12-browser (#174): a front-end change is looked at with Playwright. Plan
 * refuses a manifest that touches the home page's controller without
 * browser_testable and a look, then passes with one. Code writes the label
 * and the look's test, which gate:23 runs on the compiled site. Check holds
 * while the item is unticked and while the look fails, the engine running it
 * against the site, and passes when it is green. Complete holds the item
 * until it is kept or dropped, and completes on keep.
 */

use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\LookStep;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\SeekerStep;
use AIC\Workflow\Domain\Probe\ServeStep;
use AIC\Workflow\Domain\Probe\StopStep;
use AIC\Workflow\Domain\Probe\WriteStep;

$item = '- [ ] tests/browser/probe-home.spec.ts — the home page names the uploads demo by its new label';
$ticked = '- [x] tests/browser/probe-home.spec.ts — the home page names the uploads demo by its new label — saw: the new label';

return new ProbeScenario([
    new RunStep('blocked', ticket: 1),
    new WriteStep('var/workflow/spec-1.md', 'spec-unlooked.md.fixture'),
    new LookStep(),
    new RunStep('blocked', [new Expect('contract', 'blocked', 'touches front-end paths (modules/site/src/Controller/HomePage.php)')]),
    new WriteStep('var/workflow/spec-1.md', 'spec.md.fixture'),
    new LookStep(),
    new RunStep('advanced', [new Expect('contract', 'passed'), new Expect('grounding', 'passed')]),
    new ReplaceStep(
        'modules/site/src/Controller/HomePage.php',
        "'/uploads' => 'Uploads (checked by content)',",
        "'/uploads' => 'Uploads (each file checked by its content)',",
    ),
    new WriteStep('tests/browser/probe-home.spec.ts', 'look.spec.ts.fixture'),
    new RunStep('inspection-due', [new Expect('gate', 'passed'), new Expect('seeker', 'due')]),
    new SeekerStep(),
    new RunStep('advanced'),
    new ServeStep(),
    new RunStep('blocked', [new Expect('browser', 'failed', 'item 1 is not ticked')]),
    new WriteStep('tests/browser/probe-home.spec.ts', 'look-wrong.spec.ts.fixture'),
    new ReplaceStep('var/workflow/spec-1.md', $item, $ticked),
    new RunStep('blocked', [new Expect('browser', 'failed', 'the looks failed')]),
    new WriteStep('tests/browser/probe-home.spec.ts', 'look.spec.ts.fixture'),
    new RunStep('advanced', [new Expect('browser', 'passed', 'ran green')]),
    new RunStep('blocked', [new Expect('browser', 'failed', 'item 1 is not decided')]),
    new ReplaceStep('var/workflow/spec-1.md', $ticked, $ticked . ' — keep'),
    new ReplaceStep('var/workflow/spec-1.md', 'is checked | [ ] |', 'is checked | [x] browser |'),
    new RunStep('completed', [new Expect('browser', 'passed', 'kept or dropped')]),
    new StopStep(),
], database: true);
