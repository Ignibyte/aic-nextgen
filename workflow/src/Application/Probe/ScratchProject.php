<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The scenarios' throwaway apps (#119), made by the builder the seeker probe
 * shares (`workflow/probes/scratch-app.sh`).
 */
interface ScratchProject
{
    /**
     * @param string        $scratch       The folder the apps are made in.
     * @param string        $name          The scenario, which names the app's directory.
     * @param ProbeScenario $probeScenario Its settings and base files.
     *
     * @throws WorkflowRefusal When the app cannot be made.
     *
     * @return ProbeApp The app, its scenario's config, ticket and base files committed.
     */
    public function make(string $scratch, string $name, ProbeScenario $probeScenario): ProbeApp;

    /**
     * @param ProbeApp $probeApp An app.
     */
    public function remove(ProbeApp $probeApp): void;
}
