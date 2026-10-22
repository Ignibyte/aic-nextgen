<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function json_encode;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * One line of the run-event log (#100), `aic.run-event/1`: its keys sorted, the
 * payload last, already encoded and an object even when empty.
 */
final readonly class RunEvent
{
    /**
     * The schema every line names.
     */
    public const string SCHEMA = 'aic.run-event/1';

    /**
     * @param string    $eventId   `evt-` and 16 hex digits, the dedup key.
     * @param int       $seq       Its place in the log, from 1.
     * @param string    $runId     The run.
     * @param string    $ticketId  The run's ticket.
     * @param string    $time      When, UTC `Y-m-d\TH:i:s\Z`.
     * @param EventType $eventType What happened.
     * @param string    $payload   The payload, a JSON object.
     */
    public function __construct(
        public string $eventId,
        public int $seq,
        public string $runId,
        public string $ticketId,
        public string $time,
        public EventType $eventType,
        public string $payload,
    ) {
    }

    /**
     * @return string The line, without its line break.
     */
    public function line(): string
    {
        $head = json_encode([
            'at' => $this->time,
            'event_id' => $this->eventId,
            'run_id' => $this->runId,
            'schema' => self::SCHEMA,
            'seq' => $this->seq,
            'ticket_id' => $this->ticketId,
            'type' => $this->eventType->value,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return substr($head, 0, -1) . ',"payload":' . $this->payload . '}';
    }
}
