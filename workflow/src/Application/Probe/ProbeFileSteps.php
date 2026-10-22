<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\DeleteStep;
use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\Probe\ProbeLine;
use AIC\Workflow\Domain\Probe\ProbeStep;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\WriteStep;
use Override;

use function str_replace;
use function substr_count;

/**
 * The plants (#119): a fixture written, a path deleted, a text replaced,
 * each as the operator. A replace whose text is not in the file exactly
 * once fails, so a stale plant never reads as a catch.
 */
final readonly class ProbeFileSteps implements ProbeStepRunner
{
    /**
     * @param ProbeFiles     $probeFiles     The app's files.
     * @param ProbeScenarios $probeScenarios The fixtures.
     * @param ProbeLines     $probeLines     Makes the lines.
     */
    public function __construct(
        private ProbeFiles $probeFiles,
        private ProbeScenarios $probeScenarios,
        private ProbeLines $probeLines = new ProbeLines(),
    ) {
    }

    /**
     * @param ProbeApp  $probeApp  The app.
     * @param string    $scenario  The scenario.
     * @param string    $step      The step's name.
     * @param ProbeStep $probeStep The step.
     *
     * @return list<ProbeLine>|null Its lines; null when it is not one of this family's.
     */
    #[Override]
    public function lines(ProbeApp $probeApp, string $scenario, string $step, ProbeStep $probeStep): ?array
    {
        return match (true) {
            $probeStep instanceof WriteStep => [$this->write($probeApp, $scenario, $step, $probeStep)],
            $probeStep instanceof DeleteStep => [$this->delete($probeApp, $scenario, $step, $probeStep)],
            $probeStep instanceof ReplaceStep => [$this->replace($probeApp, $scenario, $step, $probeStep)],
            default => null,
        };
    }

    /**
     * @param ProbeApp  $probeApp  The app.
     * @param string    $scenario  The scenario.
     * @param string    $step      The step's name.
     * @param WriteStep $writeStep The plant.
     *
     * @return ProbeLine Whether it was written.
     */
    private function write(ProbeApp $probeApp, string $scenario, string $step, WriteStep $writeStep): ProbeLine
    {
        $bytes = $this->probeScenarios->fixture($scenario, $writeStep->fixture);
        $written = $bytes !== null && $this->probeFiles->write($probeApp, $writeStep->path, $bytes);

        return $this->probeLines->held($written, $scenario, $step, 'wrote ' . $writeStep->path, $bytes === null
            ? 'the scenario has no ' . $writeStep->fixture
            : 'the write failed');
    }

    /**
     * @param ProbeApp   $probeApp   The app.
     * @param string     $scenario   The scenario.
     * @param string     $step       The step's name.
     * @param DeleteStep $deleteStep The deletion.
     *
     * @return ProbeLine Whether the path was there and is gone.
     */
    private function delete(ProbeApp $probeApp, string $scenario, string $step, DeleteStep $deleteStep): ProbeLine
    {
        $deleted = $this->probeFiles->delete($probeApp, $deleteStep->path);

        return $this->probeLines->held($deleted, $scenario, $step, 'deleted ' . $deleteStep->path, 'it was not there');
    }

    /**
     * @param ProbeApp    $probeApp    The app.
     * @param string      $scenario    The scenario.
     * @param string      $step        The step's name.
     * @param ReplaceStep $replaceStep The replacement.
     *
     * @return ProbeLine Whether the text was there once and was replaced.
     */
    private function replace(ProbeApp $probeApp, string $scenario, string $step, ReplaceStep $replaceStep): ProbeLine
    {
        $bytes = $this->probeFiles->read($probeApp, $replaceStep->path) ?? '';
        $count = substr_count($bytes, $replaceStep->from);
        $replaced = $count === 1
            && $this->probeFiles->write($probeApp, $replaceStep->path, str_replace($replaceStep->from, $replaceStep->into, $bytes));

        return $this->probeLines->held(
            $replaced,
            $scenario,
            $step,
            'replaced a text in ' . $replaceStep->path,
            'the text to replace is there ' . $count . ' time(s), not once',
        );
    }
}
