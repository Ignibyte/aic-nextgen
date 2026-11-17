<?php

declare(strict_types=1);

/*
 * 14-scaffold (#299): the app's scaffolds are walled and frozen. Before a
 * run, make:scaffold is refused and the guard refuses a write under
 * scaffolds/; at code, with no approval from the operator, both are refused
 * again; and a scaffold file that differs from the frozen record holds the
 * run at the scaffolds row. The approved path needs the operator's answer
 * from a terminal, which the sweep has not; it is proven by hand.
 */

use AIC\Workflow\Domain\Probe\CommandStep;
use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\GuardStep;
use AIC\Workflow\Domain\Probe\LookStep;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

$write = static fn (string $path): string => '{"session_id":"probe","cwd":"{root}","tool_name":"Write","tool_input":'
    . '{"file_path":"{root}/' . $path . '","content":"probe"}}';
$walled = "scaffolds/ holds the app's make kinds, frozen";

return new ProbeScenario([
    new CommandStep(['php', 'bin/aic', 'make:scaffold', 'ticker', '--from=poller'], 1),
    new GuardStep('a write under scaffolds/, no run open', 'edit', $write('scaffolds/poller/job.php.tpl'), 2, $walled),
    new RunStep('blocked', ticket: 1),
    new WriteStep('var/workflow/spec-1.md', 'spec.md.fixture'),
    new LookStep(),
    new RunStep('advanced'),
    new GuardStep('a write under scaffolds/ at code, not approved', 'edit', $write('scaffolds/poller/job.php.tpl'), 2, $walled),
    new CommandStep(['php', 'bin/aic', 'make:scaffold', 'ticker', '--from=poller'], 1),
    new WriteStep('var/workflow/scaffolds.json', 'scaffolds.json.fixture'),
    new RunStep('blocked', [
        new Expect('scaffolds', 'failed', 'scaffolds/poller/job.php.tpl'),
    ]),
]);
