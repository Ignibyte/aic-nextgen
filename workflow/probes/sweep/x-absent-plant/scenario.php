<?php

declare(strict_types=1);

/*
 * x-absent-plant (#119; #104 AC 9), run only by name: a plant whose text is
 * not in its file is a failure, never a catch, and the sweep exits 1.
 */

use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;

return new ProbeScenario([
    new ReplaceStep('modules/site/src/Controller/HomePage.php', 'a text the home page does not hold', 'a plant'),
]);
