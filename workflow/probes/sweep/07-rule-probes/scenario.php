<?php

declare(strict_types=1);

/*
 * 07-rule-probes (#119; #104 AC 6): six house rules that cannot be trusted
 * (one exits 3, one defers on its held case, one is missing, one sleeps
 * past its timeout, one never bites, one has no probe) hold the run as it
 * begins, each named; the two shipped rules' probes pass. No gate runs.
 */

use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

$base = [
    new WriteStep('config/rules/exit3.php', 'exit3.php.fixture'),
    new WriteStep('config/rules/exit3.probe/base/a.txt.fixture', 'a.txt.fixture'),
    new WriteStep('config/rules/exit3.probe/held-change/a.txt.fixture', 'a-changed.txt.fixture'),
    new WriteStep('config/rules/exit3.probe/broken-add/b.txt.fixture', 'b.txt.fixture'),
    new WriteStep('config/rules/held4.php', 'held4.php.fixture'),
    new WriteStep('config/rules/held4.probe/base/a.txt.fixture', 'a.txt.fixture'),
    new WriteStep('config/rules/held4.probe/held-change/a.txt.fixture', 'a-changed.txt.fixture'),
    new WriteStep('config/rules/held4.probe/broken-add/b.txt.fixture', 'b.txt.fixture'),
    new WriteStep('config/rules/slow.php', 'slow.php.fixture'),
    new WriteStep('config/rules/slow.probe/base/a.txt.fixture', 'a.txt.fixture'),
    new WriteStep('config/rules/slow.probe/held-change/a.txt.fixture', 'a-changed.txt.fixture'),
    new WriteStep('config/rules/slow.probe/broken-add/b.txt.fixture', 'b.txt.fixture'),
    new WriteStep('config/rules/never.php', 'never.php.fixture'),
    new WriteStep('config/rules/never.probe/base/a.txt.fixture', 'a.txt.fixture'),
    new WriteStep('config/rules/never.probe/held-change/a.txt.fixture', 'a-changed.txt.fixture'),
    new WriteStep('config/rules/never.probe/broken-add/b.txt.fixture', 'b.txt.fixture'),
    new WriteStep('config/rules/noprobe.php', 'never.php.fixture'),
];

return new ProbeScenario([
    new RunStep('held', [
        new Expect('probes', 'held', 'exit3 held-change exited 3, not 0.', 'gate-suite'),
        new Expect('probes', 'held', 'held4 held-change exited 4, not 0.', 'raw-html-grants'),
        new Expect('probes', 'held', 'missing ships no probe'),
        new Expect('probes', 'held', 'slow held-change exited 124, not 0.'),
        new Expect('probes', 'held', 'never broken-add exited 0, not 1.'),
        new Expect('probes', 'held', 'noprobe ships no probe'),
    ], ticket: 1),
], base: $base);
