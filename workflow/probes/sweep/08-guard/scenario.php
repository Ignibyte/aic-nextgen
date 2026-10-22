<?php

declare(strict_types=1);

/*
 * 08-guard (#119, #102, #125): the installed guard, fed the hook payloads a
 * session would send. Before a run, a write outside the ticket directory
 * is refused; at code, an undeclared write, a write to the run's state, the
 * operator's reset in the agent's shell and a write that lands outside the
 * app through a symlink are refused, a declared write is allowed, and Stop
 * is held mid-phase.
 */

use AIC\Workflow\Domain\Probe\CommandStep;
use AIC\Workflow\Domain\Probe\GuardStep;
use AIC\Workflow\Domain\Probe\LookStep;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

$write = static fn (string $path): string => '{"session_id":"probe","cwd":"{root}","tool_name":"Write","tool_input":'
    . '{"file_path":"{root}/' . $path . '","content":"probe"}}';

return new ProbeScenario([
    new GuardStep(
        'a write outside the ticket directory, no run open',
        'edit',
        $write('stray.txt'),
        2,
        'no run is open: begin one with bin/aic workflow:run',
    ),
    new RunStep('blocked', ticket: 1),
    new WriteStep('var/workflow/spec-1.md', 'spec.md.fixture'),
    new LookStep(),
    new RunStep('advanced'),
    new CommandStep(['ln', '-s', '../..', 'linkout']),
    new GuardStep(
        'an undeclared write at code',
        'edit',
        $write('modules/note/src/Service/Stray.php'),
        2,
        "is not in the spec's ### File Manifest",
    ),
    new GuardStep('a declared write at code', 'edit', $write('modules/site/src/Controller/HomePage.php'), 0),
    new GuardStep("a write to the run's state", 'edit', $write('var/workflow/run.json'), 2, "var/workflow/ is the run's state"),
    new GuardStep(
        "the operator's reset in the agent's shell",
        'shell',
        '{"session_id":"probe","cwd":"{root}","tool_name":"Bash","tool_input":{"command":"php bin/aic workflow:reset"}}',
        2,
        "workflow:reset is the operator's",
    ),
    new GuardStep(
        'a declared write that lands outside the app through a symlink',
        'edit',
        $write('linkout/escape.txt'),
        2,
        'outside the project: the guard refuses every write that leaves it',
    ),
    new GuardStep('Stop mid-phase', 'stop', '{"session_id":"probe","cwd":"{root}","stop_hook_active":false}', 2, 'may not end mid-phase'),
]);
