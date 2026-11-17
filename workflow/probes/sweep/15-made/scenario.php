<?php

declare(strict_types=1);

/*
 * 15-made (#302): the made ledger is the engine's. At code a make writes a
 * row per file it created in .aic/made.jsonl and the made row passes; the
 * guard refuses a write to the ledger; and a ledger rewritten by hand, its
 * rows no make wrote, holds the run at the made row.
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

return new ProbeScenario([
    new RunStep('blocked', ticket: 1),
    new WriteStep('var/workflow/spec-1.md', 'spec.md.fixture'),
    new LookStep(),
    new RunStep('advanced'),
    new CommandStep(['php', 'bin/aic', 'make', 'poller', 'site', 'Ticker']),
    new RunStep('blocked', [
        new Expect('made', 'passed', "this run's 2 made file(s)"),
    ]),
    new GuardStep('a write to the made ledger at code', 'edit', $write('.aic/made.jsonl'), 2, '.aic/ holds the made ledger'),
    new WriteStep('.aic/made.jsonl', 'made.jsonl.fixture'),
    new RunStep('blocked', [
        new Expect('made', 'failed', 'no make of this run wrote'),
    ]),
]);
