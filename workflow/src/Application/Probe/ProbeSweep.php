<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\Probe\ProbeLine;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ProbeTally;
use AIC\Workflow\Domain\Probe\ProbeVerdict;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function microtime;
use function round;
use function sprintf;

/**
 * The probe sweep (#119): each scenario in its own throwaway app, one at a
 * time, its lines written as they settle, then its time and what became of
 * the app, which is removed pass or fail unless `--keep` keeps a failing
 * one. A scenario that needs the database and gets no answer from
 * `bin/aic db:ping` runs nothing: every expectation in it is not run. The
 * last line is the tally.
 */
final readonly class ProbeSweep
{
    /**
     * @param ProbeScenarios $probeScenarios The scenarios.
     * @param ScratchProject $scratchProject Makes and removes the apps.
     * @param ProbeSteps     $probeSteps     Runs a scenario's steps.
     * @param ProbeShell     $probeShell     Asks the app for the database.
     * @param ProbeServer    $probeServer    Stops a server a scenario left.
     * @param ProbeFiles     $probeFiles     Keeps the run's events as evidence.
     * @param ProbeOutput    $probeOutput    Writes the lines.
     * @param ProbeLines     $probeLines     Makes the lines.
     */
    public function __construct(
        private ProbeScenarios $probeScenarios,
        private ScratchProject $scratchProject,
        private ProbeSteps $probeSteps,
        private ProbeShell $probeShell,
        private ProbeServer $probeServer,
        private ProbeFiles $probeFiles,
        private ProbeOutput $probeOutput,
        private ProbeLines $probeLines = new ProbeLines(),
    ) {
    }

    /**
     * @param string       $scratch Where the apps are made.
     * @param list<string> $names   The scenarios, in order.
     * @param bool         $keep    Whether a failing scenario's app is kept.
     *
     * @throws WorkflowRefusal When probe evidence cannot be written completely.
     *
     * @return ProbeTally Every expectation, counted.
     */
    public function sweep(string $scratch, array $names, bool $keep): ProbeTally
    {
        $probeTally = new ProbeTally();

        foreach ($names as $name) {
            $probeTally = $probeTally->with($this->scenario($scratch, $name, $keep));
        }

        $this->probeOutput->line($probeTally->summary());

        return $probeTally;
    }

    /**
     * @param string $scratch Where its app is made.
     * @param string $name    The scenario.
     * @param bool   $keep    Whether its app is kept when it fails.
     *
     * @throws WorkflowRefusal When probe evidence cannot be written completely.
     *
     * @return list<ProbeLine> Its lines.
     */
    private function scenario(string $scratch, string $name, bool $keep): array
    {
        $started = microtime(true);
        $this->probeFiles->fresh($name);

        try {
            $probeScenario = $this->probeScenarios->load($name);
            $probeApp = $this->scratchProject->make($scratch, $name, $probeScenario);
        } catch (WorkflowRefusal $workflowRefusal) {
            $lines = [$this->probeLines->held(false, $name, '00 make the app', 'the app is made', $workflowRefusal->getMessage())];
            $this->probeOutput->line($lines[0]->text());

            return $lines;
        }

        $lines = $this->lines($probeApp, $name, $probeScenario);
        $this->finish($probeApp, $name, $lines, $keep, $started);

        return $lines;
    }

    /**
     * @param ProbeApp        $probeApp The scenario's app.
     * @param string          $name     The scenario.
     * @param list<ProbeLine> $lines    Its lines.
     * @param bool            $keep     Whether its app is kept when it failed.
     * @param float           $started  When it started.
     *
     * @throws WorkflowRefusal When probe evidence cannot be written completely.
     */
    private function finish(ProbeApp $probeApp, string $name, array $lines, bool $keep, float $started): void
    {
        $this->probeFiles->evidence($name, 'events.jsonl', $this->probeFiles->read($probeApp, 'var/workflow/events.jsonl') ?? '');
        $kept = $keep && array_any($lines, static fn (ProbeLine $probeLine): bool => $probeLine->probeVerdict !== ProbeVerdict::Pass);

        if (!$kept) {
            $this->scratchProject->remove($probeApp);
        }

        $this->probeOutput->line('scenario ' . $name . ': ' . sprintf('%s', round(microtime(true) - $started)) . ' s, the app '
            . ($kept ? 'kept at ' . $probeApp->root : 'removed'));
    }

    /**
     * @param ProbeApp      $probeApp      The app.
     * @param string        $name          The scenario.
     * @param ProbeScenario $probeScenario Its steps.
     *
     * @throws WorkflowRefusal When probe evidence cannot be written completely.
     *
     * @return list<ProbeLine> Its steps' lines, or not-run lines when it needs a database that does not answer.
     */
    private function lines(ProbeApp $probeApp, string $name, ProbeScenario $probeScenario): array
    {
        if (
            $probeScenario->database
            && $this->probeShell->run($probeApp, ['php', 'bin/aic', 'db:ping'])->exitCode !== 0
        ) {
            $lines = $this->probeLines->notRun($name, '00 db:ping', $probeScenario->expectations(), 'the database does not answer');

            foreach ($lines as $line) {
                $this->probeOutput->line($line->text());
            }

            return $lines;
        }

        try {
            return $this->probeSteps->run($probeApp, $name, $probeScenario);
        } finally {
            $this->probeServer->stop($probeApp);
        }
    }
}
