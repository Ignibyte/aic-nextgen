<?php

declare(strict_types=1);

/*
 * 01-declaration (#119; #101 AC 2, 3, 6; #104): at code an undeclared file,
 * a new RawHtml grant and an edited rule file hold the run, and it advances
 * once they are restored; at check, a raised PHPMD limit passes gate:10 but
 * breaks the gate-suite rule, the declared changelog edit makes the gate run
 * again, and once the limit is restored the rule examines nothing
 * (deferred); at complete, a reworded criterion breaks the freeze, and once
 * it is restored and the criteria are ticked, the run completes.
 */

use AIC\Workflow\Domain\Probe\DeleteStep;
use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

$probePlan = new ProbePlan();
$grants = new ReplaceStep(
    'config/raw-html.php',
    '$grants = [];',
    "\$grants = [new RawHtmlGrant('App\\\\Note\\\\Service\\\\NoteBadge', 1, 'probe')];",
);
$rule = new ReplaceStep(
    'vendor/aic/workflow/rules/gate-suite.php',
    'gate-suite: the input is not an aic.house-rule/1 object.',
    'gate-suite: the input is not an aic.house-rule/1 object (probe).',
);
$limit = new ReplaceStep(
    'gates/config/phpmd.xml',
    '<property name="minimum" value="31"/>',
    '<property name="minimum" value="41"/>',
);
$criterion = new ReplaceStep(
    'var/workflow/spec-1.md',
    '| 2 | The changelog names the fix | [ ] |',
    '| 2 | The changelog names the change | [ ] |',
);

return new ProbeScenario([
    ...$probePlan->toCode(),
    $probePlan->label(),
    ...$probePlan->inspected(),
    new WriteStep('stray.txt', 'stray.txt.fixture'),
    $grants,
    $rule,
    new RunStep('blocked', [
        new Expect('declaration', 'failed', 'stray.txt'),
        new Expect('rule:raw-html-grants', 'failed'),
        new Expect('rules', 'failed', 'gate-suite.php'),
    ]),
    new DeleteStep('stray.txt'),
    new ReplaceStep($grants->path, $grants->into, $grants->from),
    new ReplaceStep($rule->path, $rule->into, $rule->from),
    new RunStep('advanced', [new Expect('declaration', 'passed'), new Expect('rules', 'passed'), new Expect('gate', 'passed')]),
    new ReplaceStep(
        'CHANGELOG.md',
        "## Unreleased\n",
        "## Unreleased\n\n- The home page's uploads link says each file is checked by its content.\n",
    ),
    $limit,
    new RunStep('blocked', [
        new Expect('gate', 'passed', 'the last gate verdict is stale (the tree changed), so the gate ran again'),
        new Expect('rule:gate-suite', 'failed', 'phpmd.xml'),
    ]),
    new ReplaceStep($limit->path, $limit->into, $limit->from),
    new RunStep('advanced', [
        new Expect('gate', 'passed', 'the last gate verdict is stale (the tree changed), so the gate ran again'),
        new Expect('rule:gate-suite', 'deferred', 'examined nothing'),
    ]),
    $criterion,
    new RunStep('blocked', [new Expect('freeze', 'failed', 'The changelog names the fix')]),
    new ReplaceStep($criterion->path, $criterion->into, $criterion->from),
    new ReplaceStep(
        'var/workflow/spec-1.md',
        "content | [ ] |\n| 2 | The changelog names the fix | [ ] |",
        "content | [x] gate:6 |\n| 2 | The changelog names the fix | [x] gate:6 |",
    ),
    new RunStep('completed', [new Expect('freeze', 'passed'), new Expect('criteria', 'passed')]),
], database: true);
