<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\CommandStep;
use AIC\Workflow\Domain\Probe\LookStep;
use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\Probe\ProbeLine;
use AIC\Workflow\Domain\Probe\ProbeStep;
use AIC\Workflow\Domain\Probe\RunAnswer;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\ProcessResult;
use Override;

use function count;
use function explode;
use function str_replace;
use function trim;

/**
 * The steps that run the engine and the app's tools (#119): a phase call,
 * read from its JSON envelope, a command held to its exit, and plan's code
 * lookups through the shared builder. Each call's output is kept as
 * evidence.
 */
final readonly class ProbeRunSteps implements ProbeStepRunner
{
    /**
     * @param string     $builder    The shared builder, `workflow/probes/scratch-app.sh`.
     * @param ProbeShell $probeShell Runs in the app.
     * @param ProbeFiles $probeFiles Keeps the evidence.
     * @param ProbeJson  $probeJson  Reads the envelope.
     * @param ProbeLines $probeLines Makes the lines.
     */
    public function __construct(
        private string $builder,
        private ProbeShell $probeShell,
        private ProbeFiles $probeFiles,
        private ProbeJson $probeJson,
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
            $probeStep instanceof RunStep => $this->run($probeApp, $scenario, $step, $probeStep),
            $probeStep instanceof CommandStep => [$this->command($probeApp, $scenario, $step, $probeStep)],
            $probeStep instanceof LookStep => [$this->look($probeApp, $scenario, $step, $probeStep)],
            default => null,
        };
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $scenario The scenario.
     * @param string   $step     The step's name.
     * @param RunStep  $runStep  The call.
     *
     * @return list<ProbeLine> The outcome's line, then one per expected row.
     */
    private function run(ProbeApp $probeApp, string $scenario, string $step, RunStep $runStep): array
    {
        $argv = ['php', 'bin/aic', 'workflow:run', ...($runStep->ticket === 0 ? [] : [(string) $runStep->ticket])];
        $processResult = $this->probeShell->run($probeApp, $argv, $runStep->env);
        $this->keep($scenario, $step, $processResult);
        $runAnswer = $this->probeJson->run($processResult->output);

        if (!$runAnswer instanceof RunAnswer) {
            return $this->unread($scenario, $step, $runStep, $processResult);
        }

        $seen = $runAnswer->summary();
        $answered = $runAnswer->outcome === $runStep->outcome;
        $lines = [$this->probeLines->held($answered, $scenario, $step, 'answers ' . $runStep->outcome, $seen)];

        foreach ($runStep->rows as $expect) {
            $lines[] = $this->probeLines->held($runAnswer->holds($expect), $scenario, $step, $expect->text(), $seen);
        }

        return $lines;
    }

    /**
     * @param ProbeApp    $probeApp    The app.
     * @param string      $scenario    The scenario.
     * @param string      $step        The step's name.
     * @param CommandStep $commandStep The command.
     *
     * @return ProbeLine Whether it exited as expected.
     */
    private function command(ProbeApp $probeApp, string $scenario, string $step, CommandStep $commandStep): ProbeLine
    {
        $processResult = $this->probeShell->run($probeApp, $commandStep->argv, $commandStep->env);
        $this->keep($scenario, $step, $processResult);

        return $this->probeLines->held(
            $processResult->exitCode === $commandStep->exit,
            $scenario,
            $step,
            'exits ' . $commandStep->exit,
            'exit ' . $processResult->exitCode . ': ' . $this->last($processResult),
        );
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $scenario The scenario.
     * @param string   $step     The step's name.
     * @param LookStep $lookStep The lookups.
     *
     * @return ProbeLine Whether they ran.
     */
    private function look(ProbeApp $probeApp, string $scenario, string $step, LookStep $lookStep): ProbeLine
    {
        $processResult = $this->probeShell->run($probeApp, ['bash', $this->builder, 'look', $lookStep->spec]);

        return $this->probeLines->held($processResult->exitCode === 0, $scenario, $step, 'looked', $this->last($processResult));
    }

    /**
     * @param string        $scenario      The scenario.
     * @param string        $step          The step's name.
     * @param RunStep       $runStep       The call.
     * @param ProcessResult $processResult What it printed, which held no envelope.
     *
     * @return list<ProbeLine> The outcome's line, failed, and its rows not run.
     */
    private function unread(string $scenario, string $step, RunStep $runStep, ProcessResult $processResult): array
    {
        return [
            $this->probeLines->held(false, $scenario, $step, 'answers ' . $runStep->outcome, 'no envelope: ' . $this->last($processResult)),
            ...$this->probeLines->notRun($scenario, $step, count($runStep->rows), 'the call printed no envelope'),
        ];
    }

    /**
     * @param string        $scenario      The scenario.
     * @param string        $step          The step's name.
     * @param ProcessResult $processResult What it printed.
     */
    private function keep(string $scenario, string $step, ProcessResult $processResult): void
    {
        $this->probeFiles->evidence($scenario, str_replace(' ', '-', $step) . '.txt', $processResult->output);
    }

    /**
     * @param ProcessResult $processResult A call's result.
     *
     * @return string Its last line of output that is not empty.
     */
    private function last(ProcessResult $processResult): string
    {
        $last = '';

        foreach (explode("\n", $processResult->output) as $line) {
            $last = trim($line) === ''
                ? $last
                : trim($line);
        }

        return $last;
    }
}
