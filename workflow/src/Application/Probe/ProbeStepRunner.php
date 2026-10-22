<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\Probe\ProbeLine;
use AIC\Workflow\Domain\Probe\ProbeStep;

/**
 * One family of the sweep's steps (#119): the plants, the engine's and the
 * tools' calls, or the stand-ins for the agent's session. Each runs the
 * steps it knows and passes on the others.
 */
interface ProbeStepRunner
{
    /**
     * @param ProbeApp  $probeApp  The app.
     * @param string    $scenario  The scenario.
     * @param string    $step      The step's name.
     * @param ProbeStep $probeStep The step.
     *
     * @return list<ProbeLine>|null Its expectations' lines; null when it is not a step this family runs.
     */
    public function lines(ProbeApp $probeApp, string $scenario, string $step, ProbeStep $probeStep): ?array;
}
