<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\GuardStep;
use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\Probe\ProbeLine;
use AIC\Workflow\Domain\Probe\ProbeStep;
use AIC\Workflow\Domain\Probe\SeekerStep;
use AIC\Workflow\Domain\Probe\ServeStep;
use AIC\Workflow\Domain\Probe\StopStep;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function json_encode;
use function str_contains;
use function str_replace;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * The steps that stand in for the agent's session (#119): a guard call with
 * a hook payload, the seeker's ledger filed through the guard's `returned`
 * verb and `workflow:seeker-report`, and the app served and stopped for the
 * rendered check.
 */
final readonly class ProbeHookSteps implements ProbeStepRunner
{
    /**
     * The installed guard, relative to the app.
     */
    private const string GUARD = 'vendor/aic/workflow/bin/aic-guard.sh';

    /**
     * @param ProbeShell  $probeShell  Runs in the app.
     * @param ProbeServer $probeServer Serves it.
     * @param ProbeJson   $probeJson   Reads the brief.
     * @param ProbeFiles  $probeFiles  Keeps each guard answer as evidence.
     * @param ProbeLines  $probeLines  Makes the lines.
     */
    public function __construct(
        private ProbeShell $probeShell,
        private ProbeServer $probeServer,
        private ProbeJson $probeJson,
        private ProbeFiles $probeFiles,
        private ProbeLines $probeLines = new ProbeLines(),
    ) {
    }

    /**
     * @param ProbeApp  $probeApp  The app.
     * @param string    $scenario  The scenario.
     * @param string    $step      The step's name.
     * @param ProbeStep $probeStep The step.
     *
     * @throws WorkflowRefusal When probe evidence cannot be written completely.
     *
     * @return list<ProbeLine>|null Its lines; null when it is not one of this family's.
     */
    #[Override]
    public function lines(ProbeApp $probeApp, string $scenario, string $step, ProbeStep $probeStep): ?array
    {
        return match (true) {
            $probeStep instanceof GuardStep => [$this->guard($probeApp, $scenario, $step, $probeStep)],
            $probeStep instanceof SeekerStep => [$this->seeker($probeApp, $scenario, $step)],
            $probeStep instanceof ServeStep => [$this->serve($probeApp, $scenario, $step, $probeStep)],
            $probeStep instanceof StopStep => [$this->stop($probeApp, $scenario, $step)],
            default => null,
        };
    }

    /**
     * @param ProbeApp  $probeApp  The app.
     * @param string    $scenario  The scenario.
     * @param string    $step      The step's name.
     * @param GuardStep $guardStep The call.
     *
     * @throws WorkflowRefusal When probe evidence cannot be written completely.
     *
     * @return ProbeLine Whether the guard exited as expected and said what it should.
     */
    private function guard(ProbeApp $probeApp, string $scenario, string $step, GuardStep $guardStep): ProbeLine
    {
        $payload = str_replace('{root}', $probeApp->root, $guardStep->payload);
        $processResult = $this->probeShell->run($probeApp, ['bash', self::GUARD, $guardStep->verb], [], $payload);
        $this->probeFiles->evidence($scenario, $step . '.txt', 'exit ' . $processResult->exitCode . "\n" . $processResult->output);
        $held = $processResult->exitCode === $guardStep->exit && str_contains($processResult->output, $guardStep->contains);

        return $this->probeLines->held(
            $held,
            $scenario,
            $step,
            'exits ' . $guardStep->exit . ($guardStep->contains === '' ? '' : ' saying "' . $guardStep->contains . '"'),
            'exit ' . $processResult->exitCode . ': ' . trim($processResult->output),
        );
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $scenario The scenario.
     * @param string   $step     The step's name.
     *
     * @return ProbeLine Whether a clean ledger was filed for the open run.
     */
    private function seeker(ProbeApp $probeApp, string $scenario, string $step): ProbeLine
    {
        $subject = $this->probeJson->subject($this->probeShell->run($probeApp, ['php', 'bin/aic', 'workflow:seeker-brief'])->output);
        $ledger = "## Seeker Inspection\n\nInspector: independent\nSubject: " . $subject . "\n\n(no findings)\n";
        $processResult = $this->probeShell->run($probeApp, ['bash', self::GUARD, 'returned'], [], $this->returned($probeApp, $ledger));
        $report = $this->probeShell->run($probeApp, ['php', 'bin/aic', 'workflow:seeker-report'], [], $ledger);
        $filed = $subject !== '' && $processResult->exitCode === 0 && $report->exitCode === 0;

        return $this->probeLines->held($filed, $scenario, $step, 'filed a clean ledger', $subject === ''
            ? 'the brief named no subject'
            : 'returned exit ' . $processResult->exitCode . ', report exit ' . $report->exitCode . ': ' . trim($report->output));
    }

    /**
     * @param ProbeApp  $probeApp  The app.
     * @param string    $scenario  The scenario.
     * @param string    $step      The step's name.
     * @param ServeStep $serveStep The server's settings.
     *
     * @return ProbeLine Whether the app answers on its port.
     */
    private function serve(ProbeApp $probeApp, string $scenario, string $step, ServeStep $serveStep): ProbeLine
    {
        $served = $this->probeServer->start($probeApp, $serveStep->env);

        return $this->probeLines->held($served, $scenario, $step, 'serves ' . $probeApp->site(), 'nothing answered');
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $scenario The scenario.
     * @param string   $step     The step's name.
     *
     * @return ProbeLine Whether the server is gone.
     */
    private function stop(ProbeApp $probeApp, string $scenario, string $step): ProbeLine
    {
        return $this->probeLines->held($this->probeServer->stop($probeApp), $scenario, $step, 'stopped the server', 'it still runs');
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $ledger   The ledger the seeker returns.
     *
     * @return string The PostToolUse payload of an `aic-seeker` Agent call that returned it.
     */
    private function returned(ProbeApp $probeApp, string $ledger): string
    {
        return json_encode([
            'cwd' => $probeApp->root,
            'hook_event_name' => 'PostToolUse',
            'session_id' => 'probe',
            'tool_input' => ['description' => 'Inspect the open run', 'prompt' => 'Inspect the open run.', 'subagent_type' => 'aic-seeker'],
            'tool_name' => 'Agent',
            'tool_response' => ['agentType' => 'aic-seeker', 'content' => [['text' => $ledger, 'type' => 'text']], 'status' => 'completed'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
