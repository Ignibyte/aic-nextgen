<?php

declare(strict_types=1);

/*
 * 13-amend (#267): the operator's amendment of a frozen spec row. At code, workflow:amend is
 * refused without a terminal and walled in the agent's shell. Under a pseudo-terminal it refuses
 * a row the freeze does not hold and a change that is not one row for another, then swaps the
 * frozen decision in the freeze and in the run's spec and logs spec.amended, and code's exit
 * passes the freeze on the amended row.
 */

use AIC\Workflow\Domain\Probe\CommandStep;
use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\GuardStep;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\RunStep;

$probePlan = new ProbePlan();
$row = "1. Only the label's text changes.";
$newRow = "1. Only the label's words change.";
$frozen = <<<'PHP'
$pdo = new PDO('sqlite:var/workflow/evidence.sqlite');
$text = (string) $pdo->query('SELECT spec_text FROM run')->fetchColumn();
$ok = str_contains($text, "1. Only the label's words change.") && !str_contains($text, "1. Only the label's text changes.");
fwrite($ok ? STDOUT : STDERR, $ok ? "the freeze holds the amended row\n" : "the freeze does not hold the amended row\n");
exit($ok ? 0 : 1);
PHP;
$operator = static fn (string $from, string $into): CommandStep => new CommandStep(
    ['script', '-qec', 'php bin/aic workflow:amend "' . $from . '" "' . $into . '" "probe"', '/dev/null'],
    $from === $row && $into === $newRow ? 0 : 2,
);

return new ProbeScenario([
    ...$probePlan->toCode(),
    $probePlan->label(),
    new CommandStep(['php', 'bin/aic', 'workflow:amend', $row, $newRow, 'probe'], 2),
    new GuardStep(
        "the operator's amendment in the agent's shell",
        'shell',
        '{"session_id":"probe","cwd":"{root}","tool_name":"Bash","tool_input":{"command":"php bin/aic workflow:amend x y z"}}',
        2,
        "workflow:amend is the operator's",
    ),
    $operator('1. No such decision.', $newRow),
    $operator($row, '| CHANGELOG.md | modify | A second row |'),
    $operator($row, $newRow),
    new CommandStep(['grep', '-qxF', $newRow, 'var/workflow/spec-1.md']),
    new CommandStep(['php', '-r', $frozen]),
    new CommandStep(['grep', '-qF', '"type":"spec.amended"', 'var/workflow/events.jsonl']),
    new RunStep('inspection-due', [new Expect('freeze', 'passed'), new Expect('gate', 'passed'), new Expect('seeker', 'due')]),
]);
