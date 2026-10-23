<?php

declare(strict_types=1);

/*
 * 10-seeker-recovery (#145): a failed compiler leaves inspection pending,
 * not actionable. Repairing only the environment permits its first dispatch.
 * An unchanged repeat after that actionable report must still hold.
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
    new RunStep('blocked', [
        new Expect('gate', 'failed'),
        new Expect('seeker', 'due'),
    ], ['ELEPHC_BIN' => '/nonexistent-aic-probe-compiler']),
    new CommandStep(['php', 'bin/aic', 'workflow:logs', 'gate:21', '--phase=code', '--attempt=1', '--json'], 0),
    new CommandStep(['php', 'bin/aic', 'workflow:logs', 'gate', '--phase=code', '--attempt=1'], 0),
    new CommandStep(['php', 'bin/aic', 'workflow:logs', 'gate:21', '--from-line=1', '--lines=2'], 0),
    new CommandStep(['php', 'bin/aic', 'workflow:logs', 'gate:21', '--attempt=0'], 2),
    new CommandStep(['php', 'bin/aic', 'workflow:logs', 'gate:999', '--attempt=1'], 1),
    new RunStep('inspection-due', [new Expect('gate', 'passed'), new Expect('seeker', 'due')]),
    new RunStep('held', [new Expect('seeker', 'held', 'unchanged since attempt 2')]),
], database: true);
