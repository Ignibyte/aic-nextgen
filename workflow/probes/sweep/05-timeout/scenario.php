<?php

declare(strict_types=1);

/*
 * 05-timeout (#119; #101 AC 8): with gateTimeout 5, the gate run is stopped
 * before its verdict (exit 124) and the run is held. Owned lifecycle
 * controls measure the actual call and identify surviving workers (#137).
 */

use AIC\Workflow\Domain\Probe\CommandStep;
use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

$probePlan = new ProbePlan();

return new ProbeScenario([
    ...$probePlan->toCode(),
    $probePlan->label(),
    new RunStep('held', [new Expect('gate', 'held', 'the gate run passed its timeout and was stopped (exit 124)')]),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'clean']),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'nonzero']),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'large']),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'ignore']),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'grandchild']),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'early']),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'closed']),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'input']),
    new CommandStep(['php', 'var/probe05/lifecycle.php', 'sentinel']),
], base: [
    new WriteStep('var/probe05/lifecycle.php', 'lifecycle.php.fixture'),
    new WriteStep('var/probe05/process-tree.sh', 'process-tree.sh.fixture'),
]);
