<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Application\SeekerDigest;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\Guard\GuardRow;
use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\Guard\HookCall;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\WorkflowClient;

use function in_array;
use function str_starts_with;

/**
 * The guard's verbs (#102): `edit` (the editors and MCP tools with a path),
 * `shell` (Bash), `record` (every other tool) and `stop`; and `returned`
 * (#103, PostToolUse `Agent`), whose row names the subagent that returned and
 * the digest of the ledger it returned. A state it cannot read refuses; an
 * input it cannot read is allowed, as AIC's hooks are without a transcript.
 * Every call leaves one ledger row: `record` leaves none for the tools the
 * other verbs own.
 */
final readonly class GuardHook
{
    /**
     * The tools the `edit` and `shell` verbs own.
     */
    private const array WALLED = ['Write', 'Edit', 'MultiEdit', 'NotebookEdit', 'Bash'];

    /**
     * @param EditWall     $editWall     The editors' wall.
     * @param ShellWall    $shellWall    The shell's wall.
     * @param StopHold     $stopHold     The Stop hold.
     * @param GuardLedger  $guardLedger  The ledger.
     * @param UtcTime      $utcTime      The clock.
     * @param SeekerDigest $seekerDigest Digests a returned ledger (#103).
     */
    public function __construct(
        private EditWall $editWall,
        private ShellWall $shellWall,
        private StopHold $stopHold,
        private GuardLedger $guardLedger,
        private UtcTime $utcTime,
        private SeekerDigest $seekerDigest = new SeekerDigest(),
    ) {
    }

    /**
     * @param string        $verb       `edit`, `shell`, `record` or `stop`.
     * @param HookCall|null $hookCall   The call; null when its input could not be read.
     * @param GuardScene    $guardScene The run and the levers.
     *
     * @return GuardVerdict The verdict, ledgered.
     */
    public function handle(string $verb, ?HookCall $hookCall, GuardScene $guardScene): GuardVerdict
    {
        $tool = $hookCall instanceof HookCall
            ? $hookCall->tool
            : '';

        if ($verb === 'record' && $this->legacy($hookCall) && $this->owned($tool)) {
            return new GuardVerdict();
        }

        $guardVerdict = $this->judge($verb, $hookCall, $guardScene);
        $this->ledger($verb, $guardVerdict, $guardScene, $hookCall ?? new HookCall($tool, [], '', '', ''));

        return $guardVerdict;
    }

    /**
     * @param string $tool The actual tool name.
     *
     * @return bool Whether another legacy callback owns its ledger row.
     */
    private function owned(string $tool): bool
    {
        return in_array($tool, self::WALLED, true) || str_starts_with($tool, 'mcp__');
    }

    /**
     * @param HookCall|null $hookCall The actual call, when readable.
     *
     * @return bool Whether legacy callbacks must deduplicate their records.
     */
    private function legacy(?HookCall $hookCall): bool
    {
        return $hookCall instanceof HookCall && $hookCall->workflowClient === WorkflowClient::Claude;
    }

    /**
     * @param string        $verb       The verb.
     * @param HookCall|null $hookCall   The call.
     * @param GuardScene    $guardScene The run and the levers.
     *
     * @return GuardVerdict The verdict.
     */
    private function judge(string $verb, ?HookCall $hookCall, GuardScene $guardScene): GuardVerdict
    {
        if (!$hookCall instanceof HookCall) {
            return new GuardVerdict('allow', 'unreadable');
        }

        if ($guardScene->problem !== '' && $verb !== 'record') {
            return new GuardVerdict('refuse', 'state', "the guard cannot read the run's state, so it refuses: " . $guardScene->problem);
        }

        return match ($verb) {
            'edit' => $this->editWall->check($hookCall, $guardScene),
            'shell' => $this->shellWall->check($hookCall, $guardScene),
            'stop' => $this->stopHold->check($guardScene),
            default => new GuardVerdict(),
        };
    }

    /**
     * @param string       $verb         The verb.
     * @param GuardVerdict $guardVerdict The verdict.
     * @param GuardScene   $guardScene   The run.
     * @param HookCall     $hookCall     The call: its tool, its session, and a returned subagent.
     */
    private function ledger(string $verb, GuardVerdict $guardVerdict, GuardScene $guardScene, HookCall $hookCall): void
    {
        $runState = $guardScene->runState;
        $run = $runState instanceof RunState
            ? $runState->runStart->runId
            : '';
        $phase = $guardScene->phase();

        $this->guardLedger->append(new GuardRow(
            $this->utcTime->now(),
            $run,
            $phase instanceof Phase ? $phase->value : '',
            $verb,
            $hookCall->tool,
            $guardVerdict->verdict,
            $guardVerdict->rule,
            $hookCall->agent,
            $hookCall->returned,
            $hookCall->returned === '' ? '' : $this->seekerDigest->digest($hookCall->text),
        ));
    }
}
