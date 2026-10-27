<?php

declare(strict_types=1);

/*
 * 11-restart (#172): a run that begins on a tree already holding its change,
 * as after workflow:reset keeps the earlier run's work, plans on the unchanged
 * spec. The rows are judged against the run's base commit: the modified file,
 * the deleted file (gone from the tree) and the created file (already on disk)
 * all resolve, and plan advances.
 */

use AIC\Workflow\Domain\Probe\DeleteStep;
use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\LookStep;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

return new ProbeScenario([
    new ReplaceStep(
        'modules/site/src/Service/StatusBody.php',
        "The database server's version, or `unavailable`.",
        "The database server's version; `unavailable` when it does not answer.",
    ),
    new DeleteStep('modules/site/resources/upload.png'),
    new WriteStep('modules/site/src/Service/RestartNote.php', 'restart-note.php.fixture'),
    new RunStep('blocked', ticket: 1),
    new WriteStep('var/workflow/spec-1.md', 'spec.md.fixture'),
    new LookStep(),
    new RunStep('advanced', [new Expect('grounding', 'passed')]),
]);
