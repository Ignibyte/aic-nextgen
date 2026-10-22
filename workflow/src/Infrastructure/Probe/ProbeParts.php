<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Probe\ProbeFileSteps;
use AIC\Workflow\Application\Probe\ProbeHookSteps;
use AIC\Workflow\Application\Probe\ProbeRunSteps;
use AIC\Workflow\Application\Probe\ProbeSteps;
use AIC\Workflow\Application\Probe\ProbeSweep;
use AIC\Workflow\Application\Probe\WorkflowProbeCommand;

use function sys_get_temp_dir;

/**
 * `workflow:probe`, composed (#119): the scenarios in the package's
 * `probes/sweep/`, the apps made by its `probes/scratch-app.sh` from the
 * project's working tree in the system's temporary folder (`--scratch`
 * names another), and the evidence in the project's `var/probe/`.
 */
final readonly class ProbeParts
{
    /**
     * This package's root.
     */
    private const string PACKAGE = __DIR__ . '/../../..';

    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return array<string, Command> The command, by name.
     */
    public function commands(): array
    {
        $probeScenarioFile = new ProbeScenarioFile(self::PACKAGE . '/probes/sweep');
        $localProbeFiles = new LocalProbeFiles($this->root . '/var/probe');
        $localProbeShell = new LocalProbeShell();
        $probeSite = new ProbeSite();
        $stdoutProbeOutput = new StdoutProbeOutput();
        $probeSweep = new ProbeSweep(
            $probeScenarioFile,
            new ScratchApp($this->root, $probeScenarioFile, $localProbeFiles),
            $this->steps($probeScenarioFile, $localProbeFiles, $localProbeShell, $probeSite, $stdoutProbeOutput),
            $localProbeShell,
            $probeSite,
            $localProbeFiles,
            $stdoutProbeOutput,
        );
        $localProbeHost = new LocalProbeHost($this->root, self::PACKAGE);
        $scratch = sys_get_temp_dir() . '/aic-probe';

        return ['workflow:probe' => new WorkflowProbeCommand($probeSweep, $probeScenarioFile, $localProbeHost, $scratch)];
    }

    /**
     * @param ProbeScenarioFile $probeScenarioFile The scenarios.
     * @param LocalProbeFiles   $localProbeFiles   The app's files and the evidence.
     * @param LocalProbeShell   $localProbeShell   Runs in the app.
     * @param ProbeSite         $probeSite         Serves it.
     * @param StdoutProbeOutput $stdoutProbeOutput Writes the lines.
     *
     * @return ProbeSteps The step runners.
     */
    private function steps(
        ProbeScenarioFile $probeScenarioFile,
        LocalProbeFiles $localProbeFiles,
        LocalProbeShell $localProbeShell,
        ProbeSite $probeSite,
        StdoutProbeOutput $stdoutProbeOutput,
    ): ProbeSteps {
        $jsonProbeAnswers = new JsonProbeAnswers();

        return new ProbeSteps(
            [
                new ProbeFileSteps($localProbeFiles, $probeScenarioFile),
                new ProbeRunSteps(self::PACKAGE . '/probes/scratch-app.sh', $localProbeShell, $localProbeFiles, $jsonProbeAnswers),
                new ProbeHookSteps($localProbeShell, $probeSite, $jsonProbeAnswers, $localProbeFiles),
            ],
            $stdoutProbeOutput,
        );
    }
}
