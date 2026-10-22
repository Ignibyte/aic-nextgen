<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Application\EvidenceLog;
use AIC\Workflow\Domain\Guard\GuardRow;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_slice;
use function array_values;

/**
 * Moves the guard's ledger into the evidence store (#102, Droost's
 * `ingestGuardCalls`): at the top of each `workflow:run`, this run's rows
 * past the count the store already holds become `tool_call` rows. Only the
 * engine writes the store. An ingest that fails is a note, never a hold.
 */
final readonly class ToolCallIngest
{
    /**
     * @param GuardLedger $guardLedger The hook's rows.
     * @param EvidenceLog $evidenceLog The store.
     */
    public function __construct(
        private GuardLedger $guardLedger,
        private EvidenceLog $evidenceLog,
    ) {
    }

    /**
     * @param RunState $runState The open run.
     *
     * @return list<string> A note when the ledger could not be ingested; none otherwise.
     */
    public function ingest(RunState $runState): array
    {
        $runId = $runState->runStart->runId;
        $rows = array_values(array_filter($this->guardLedger->rows(), static fn (GuardRow $guardRow): bool => $guardRow->run === $runId));

        try {
            $fresh = array_slice($rows, $this->evidenceLog->toolCallCount($runId));

            if ($fresh !== []) {
                $this->evidenceLog->recordToolCalls($runId, $fresh);
            }
        } catch (WorkflowRefusal $workflowRefusal) {
            return ['the guard ledger was not ingested: ' . $workflowRefusal->getMessage()];
        }

        return [];
    }
}
