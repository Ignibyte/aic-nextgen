<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\EventLog;
use AIC\Workflow\Application\RunStore;
use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Records a make in the open run (#122): one `code.made` event with the
 * command, what it made, the phase, and each file it wrote with the hash of
 * its bytes. With no run open nothing is recorded; bookkeeping never breaks
 * a make that wrote.
 */
final readonly class MakeRecorder
{
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
     * @param string         $command The make, such as `make:page`.
     * @param string         $target  What it made.
     * @param list<MadeFile> $files   What it wrote, the manifest last.
     *
     * @return string A line for the answer's end when the run could not be read; empty when the make was recorded or no
     *                run is open (the guard has spoken already when the project needs one).
     */
    public function record(string $command, string $target, array $files): string
    {
        try {
            $runState = $this->runStore->load();
        } catch (WorkflowRefusal $workflowRefusal) {
            return 'the run could not be read, so this make is not recorded: ' . $workflowRefusal->getMessage();
        }

        $phase = $runState instanceof RunState
            ? $runState->currentPhase
            : null;

        if (!$runState instanceof RunState || !$phase instanceof Phase) {
            return '';
        }

        return $this->eventLog->append($runState, EventType::CodeMade, json_encode([
            'command' => $command,
            'files' => array_map(static fn (MadeFile $madeFile): array => ['hash' => $madeFile->hash(), 'path' => $madeFile->path], $files),
            'phase' => $phase->value,
            'target' => $target,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
