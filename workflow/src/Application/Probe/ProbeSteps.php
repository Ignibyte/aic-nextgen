<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\Probe\ProbeLine;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ProbeStep;
use AIC\Workflow\Domain\Probe\ProbeVerdict;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function array_slice;

/**
 * A scenario's steps, run in order in its app (#119). A step that does not
 * hold ends the scenario, and every expectation of the steps after it is
 * not run. Each line is written as soon as it is settled.
 */
final readonly class ProbeSteps
{
    /**
     * @param list<ProbeStepRunner> $probeStepRunners The step families: the plants, the engine's and tools' calls, and
     *                                                the stand-ins for the agent's session.
     * @param ProbeOutput           $probeOutput      Writes each line.
     * @param ProbeLines            $probeLines       Makes the lines.
     */
    public function __construct(
        private array $probeStepRunners,
        private ProbeOutput $probeOutput,
        private ProbeLines $probeLines = new ProbeLines(),
    ) {
    }

    /**
     * @param ProbeApp      $probeApp      The app.
     * @param string        $scenario      The scenario.
     * @param ProbeScenario $probeScenario Its steps.
     *
     * @throws WorkflowRefusal When probe evidence cannot be written completely.
     *
     * @return list<ProbeLine> Every expectation's line.
     */
    public function run(ProbeApp $probeApp, string $scenario, ProbeScenario $probeScenario): array
    {
        $lines = [];

        foreach ($probeScenario->steps as $index => $probeStep) {
            $step = $this->probeLines->step($index + 1, $probeStep);
            $stepLines = $this->step($probeApp, $scenario, $step, $probeStep);
            $this->write($stepLines);
            $lines = [...$lines, ...$stepLines];

            if (array_any($stepLines, static fn (ProbeLine $probeLine): bool => $probeLine->probeVerdict !== ProbeVerdict::Pass)) {
                $left = $this->left($scenario, array_slice($probeScenario->steps, $index + 1), $index + 2);
                $this->write($left);

                return [...$lines, ...$left];
            }
        }

        return $lines;
    }

    /**
     * @param ProbeApp  $probeApp  The app.
     * @param string    $scenario  The scenario.
     * @param string    $step      The step's name.
     * @param ProbeStep $probeStep The step.
     *
     * @throws WorkflowRefusal When probe evidence cannot be written completely.
     *
     * @return list<ProbeLine> Its expectations' lines.
     */
    private function step(ProbeApp $probeApp, string $scenario, string $step, ProbeStep $probeStep): array
    {
        foreach ($this->probeStepRunners as $probeStepRunner) {
            $lines = $probeStepRunner->lines($probeApp, $scenario, $step, $probeStep);

            if ($lines !== null) {
                return $lines;
            }
        }

        return [$this->probeLines->held(false, $scenario, $step, 'a step the sweep knows')];
    }

    /**
     * @param string          $scenario The scenario.
     * @param list<ProbeStep> $steps    The steps after the one that failed.
     * @param int             $first    The first one's number.
     *
     * @return list<ProbeLine> A not-run line per expectation they hold.
     */
    private function left(string $scenario, array $steps, int $first): array
    {
        $lines = [];

        foreach ($steps as $offset => $probeStep) {
            $step = $this->probeLines->step($first + $offset, $probeStep);
            $lines = [...$lines, ...$this->probeLines->notRun($scenario, $step, $probeStep->expectations(), 'an earlier step failed')];
        }

        return $lines;
    }

    /**
     * @param list<ProbeLine> $lines Settled lines.
     */
    private function write(array $lines): void
    {
        foreach ($lines as $line) {
            $this->probeOutput->line($line->text());
        }
    }
}
