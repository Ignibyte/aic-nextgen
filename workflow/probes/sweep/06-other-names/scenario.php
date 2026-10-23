<?php

declare(strict_types=1);

/*
 * 06-other-names (#119; #104 AC 8): four declared violations, caught by five gates: a
 * template calling a function (gate:25), a Page in
 * src/Entity/ (gate:26), an ungranted RawHtml (gate:27) and markup in a
 * controller's string (gate:8). Ordinary Psalm also catches the unused classes
 * (gate:13). One code exit names all five.
 */

use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

return new ProbeScenario([
    ...(new ProbePlan())->toCode(),
    new ReplaceStep(
        'modules/note/templates/components/NoteCard.html.php',
        '<?= $noteCard->note->title ?>',
        '<?= strtoupper($noteCard->note->title) ?>',
    ),
    new WriteStep('modules/note/src/Entity/ProbePage.php', 'ProbePage.php.fixture'),
    new WriteStep('modules/note/src/Service/ProbeRaw.php', 'ProbeRaw.php.fixture'),
    new WriteStep('modules/note/src/Controller/ProbeLiteralPage.php', 'ProbeLiteralPage.php.fixture'),
    new RunStep('blocked', [
        new Expect('gate', 'failed', 'gate:8'),
        new Expect('gate', 'failed', 'gate:13'),
        new Expect('gate', 'failed', 'gate:25'),
        new Expect('gate', 'failed', 'gate:26'),
        new Expect('gate', 'failed', 'gate:27'),
    ]),
], database: true);
