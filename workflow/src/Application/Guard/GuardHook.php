<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\Guard\GuardRow;
use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\Guard\HookCall;
use AIC\Workflow\Domain\RunState;

use function in_array;
use function str_starts_with;

/**
 * The guard's four verbs (#102): `edit` (the editors and MCP tools with a
 * path), `shell` (Bash), `record` (every other tool) and `stop`. A state it
 * cannot read refuses; an input it cannot read is allowed, as AIC's hooks
 * are without a transcript. Every call leaves one ledger row: `record`
 * leaves none for the tools the other verbs own.
 */
final readonly class GuardHook
{
    /**
     * The tools the `edit` and `shell` verbs own.
     */
    private const array WALLED = ['Write', 'Edit', 'MultiEdit', 'NotebookEdit', 'Bash'];

    /**
     * @param EditWall    $editWall    The editors' wall.
     * @param ShellWall   $shellWall   The shell's wall.
     * @param StopHold    $stopHold    The Stop hold.
     * @param GuardLedger $guardLedger The ledger.
     * @param UtcTime     $utcTime     The clock.
     */
    public function __construct(
        private EditWall $editWall,
        private ShellWall $shellWall,
        private StopHold $stopHold,
        private GuardLedger $guardLedger,
        private UtcTime $utcTime,
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

        if ($verb === 'record' && (in_array($tool, self::WALLED, true) || str_starts_with($tool, 'mcp__'))) {
            return new GuardVerdict();
        }

        $guardVerdict = $this->judge($verb, $hookCall, $guardScene);
        $this->ledger($verb, $tool, $guardVerdict, $guardScene, $hookCall instanceof HookCall ? $hookCall->agent : '');

        return $guardVerdict;
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
     * @param string       $tool         The tool.
     * @param GuardVerdict $guardVerdict The verdict.
     * @param GuardScene   $guardScene   The run.
     * @param string       $agent        The session.
     */
    private function ledger(
        string $verb,
        string $tool,
        GuardVerdict $guardVerdict,
        GuardScene $guardScene,
        string $agent,
    ): void {
        $runState = $guardScene->runState;
        $run = $runState instanceof RunState
            ? $runState->runStart->runId
            : '';
        $phase = $guardScene->phase();

        $this->guardLedger->append(new GuardRow(
            $this->utcTime->now(),
            $run,
            $phase->value ?? '',
            $verb,
            $tool,
            $guardVerdict->verdict,
            $guardVerdict->rule,
            $agent,
        ));
    }
}
