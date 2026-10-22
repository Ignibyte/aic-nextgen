<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The operator's way out of a run (#100): it archives the open run
 * unfinished and leaves its ticket as it is. Only from a terminal: the
 * agent's shell is refused, and #102's guard walls it too.
 */
final readonly class RunReset
{
    /**
     * @param RunStore   $runStore   The run's store.
     * @param RunJournal $runJournal The `run.reset` event.
     * @param RunHost    $runHost    Whether an operator is at a terminal.
     */
    public function __construct(
        private RunStore $runStore,
        private RunJournal $runJournal,
        private RunHost $runHost,
    ) {
    }

    /**
     * @throws WorkflowRefusal When no operator is at a terminal, or no run is open.
     *
     * @return list<string> What it did, then any notes.
     */
    public function reset(): array
    {
        if (!$this->runHost->operatorTerminal()) {
            throw new WorkflowRefusal("workflow:reset is the operator's: run it from a terminal, not an agent's shell.");
        }

        $runState = $this->runStore->load();

        if (!$runState instanceof RunState) {
            throw new WorkflowRefusal('no run is open; there is nothing to reset.');
        }

        $archivedTo = $this->runStore->archive($runState);

        return [
            'run ' . $runState->runStart->runId . ' on ticket ' . $runState->ticket->id . ' is archived unfinished at ' . $archivedTo . '.',
            ...$this->runJournal->reset($runState, $archivedTo),
        ];
    }
}
