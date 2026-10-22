<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\RunState;

/**
 * The append-only run-event log (#100). An event that cannot be written never
 * fails or holds the run: it comes back as a note.
 */
interface EventLog
{
    /**
     * @param RunState  $runState  The run the event is about.
     * @param EventType $eventType What happened.
     * @param string    $payload   The payload, a JSON object.
     *
     * @return string Empty when the line was written; a note saying why not otherwise.
     */
    public function append(RunState $runState, EventType $eventType, string $payload): string;
}
