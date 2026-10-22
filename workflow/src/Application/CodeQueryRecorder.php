<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeAnswer;
use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_slice;
use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Records a code lookup for the open run (#106, droost-workflow's
 * `ToolCallLog`): one `code.queried` event in the run's event log, with the
 * files the answer showed and the areas it outlined, so a plan check can
 * read what the agent looked at. With no run open nothing is recorded, and
 * the answer says so. Bookkeeping never breaks the lookup: a line that
 * cannot be written is a note.
 */
final readonly class CodeQueryRecorder
{
    /**
     * The files one event carries at most.
     */
    private const int FILES = 500;

    /**
     * @param RunStore $runStore The open run.
     * @param EventLog $eventLog Its event log.
     */
    public function __construct(
        private RunStore $runStore,
        private EventLog $eventLog,
    ) {
    }

    /**
     * @param string     $command    `code:tree`, `code:search` or `code:refs`.
     * @param string     $target     What it was asked about; empty for the overview.
     * @param CodeAnswer $codeAnswer The answer.
     *
     * @return string A line for the answer's end when the lookup was not recorded; empty when it was.
     */
    public function record(string $command, string $target, CodeAnswer $codeAnswer): string
    {
        try {
            $runState = $this->runStore->load();
        } catch (WorkflowRefusal $workflowRefusal) {
            return 'the run could not be read, so this lookup is not recorded: ' . $workflowRefusal->getMessage();
        }

        $phase = $runState instanceof RunState
            ? $runState->currentPhase
            : null;

        if (!$runState instanceof RunState || !$phase instanceof Phase) {
            return 'no run is open: this lookup is not recorded for a plan.';
        }

        return $this->eventLog->append($runState, EventType::CodeQueried, json_encode([
            'command' => $command,
            'files' => array_slice($codeAnswer->files, 0, self::FILES),
            'outcome' => $codeAnswer->found ? 'ok' : 'fail',
            'phase' => $phase->value,
            'scopes' => $codeAnswer->scopes,
            'target' => $target,
            'truncated' => count($codeAnswer->files) > self::FILES,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
