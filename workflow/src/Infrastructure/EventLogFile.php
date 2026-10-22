<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\EventLog;
use AIC\Workflow\Application\RunHost;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\RunEvent;
use AIC\Workflow\Domain\RunState;
use Override;
use RuntimeException;
use SplFileObject;

use function array_shift;
use function count;
use function explode;
use function is_array;
use function is_int;
use function json_decode;
use function min;
use function strlen;
use function trim;

use const LOCK_EX;
use const LOCK_UN;
use const SEEK_END;

/**
 * `var/workflow/events.jsonl` (#100): one event per line, appended under an
 * exclusive lock, `seq` one past the last parsable line. A torn last line (a
 * writer killed mid-line) stays a line of its own that readers skip. A line
 * that cannot be written is a note, never a failure (Droost's `RunEventLog`).
 */
final readonly class EventLogFile implements EventLog
{
    /**
     * How far back the tail is read at a time.
     */
    private const int BLOCK = 65_536;

    /**
     * @param string     $root       The project's root.
     * @param UtcTime    $utcTime    The clock.
     * @param RunHost    $runHost    Makes the event ids.
     * @param AtomicFile $atomicFile Makes the directory.
     */
    public function __construct(
        private string $root,
        private UtcTime $utcTime,
        private RunHost $runHost,
        private AtomicFile $atomicFile = new AtomicFile(),
    ) {
    }

    /**
     * @param RunState  $runState  The run.
     * @param EventType $eventType What happened.
     * @param string    $payload   The payload, a JSON object.
     *
     * @return string Empty when written; a note saying why not otherwise.
     */
    #[Override]
    public function append(RunState $runState, EventType $eventType, string $payload): string
    {
        try {
            $this->atomicFile->directory($this->root . '/' . RunStateFile::DIRECTORY);
            $splFileObject = new SplFileObject($this->root . '/' . RunStateFile::DIRECTORY . '/events.jsonl', 'c+');
        } catch (RuntimeException $runtimeException) {
            return $this->note($eventType, $runtimeException->getMessage());
        }

        if (!$splFileObject->flock(LOCK_EX)) {
            return $this->note($eventType, 'the log could not be locked');
        }

        try {
            return $this->write($splFileObject, $runState, $eventType, $payload);
        } finally {
            $splFileObject->flock(LOCK_UN);
        }
    }

    /**
     * @param SplFileObject $splFileObject The log, locked.
     * @param RunState      $runState      The run.
     * @param EventType     $eventType     What happened.
     * @param string        $payload       The payload.
     *
     * @return string Empty when written; a note otherwise.
     */
    private function write(
        SplFileObject $splFileObject,
        RunState $runState,
        EventType $eventType,
        string $payload,
    ): string {
        $size = $splFileObject->fstat()['size'];
        $runEvent = new RunEvent(
            $this->runHost->newId('evt', 8),
            $this->lastSeq($splFileObject, $size) + 1,
            $runState->runStart->runId,
            $runState->ticket->id,
            $this->utcTime->now(),
            $eventType,
            $payload,
        );
        $record = ($this->endsWithNewline($splFileObject, $size) ? '' : "\n") . $runEvent->line() . "\n";

        if (
            $splFileObject->fseek(0, SEEK_END) !== 0
            || $splFileObject->fwrite($record) !== strlen($record)
            || !$splFileObject->fflush()
        ) {
            return $this->note($eventType, 'the line could not be written');
        }

        return '';
    }

    /**
     * @param SplFileObject $splFileObject The log.
     * @param int           $size          Its size.
     *
     * @return bool Whether it is empty or ends in a line break: no torn line to keep apart.
     */
    private function endsWithNewline(SplFileObject $splFileObject, int $size): bool
    {
        if ($size === 0) {
            return true;
        }

        $splFileObject->fseek($size - 1);

        return $splFileObject->fread(1) === "\n";
    }

    /**
     * @param SplFileObject $splFileObject The log.
     * @param int           $size          Its size.
     *
     * @return int The last parsable line's `seq`, read back from the end a block at a time; 0 for none.
     */
    private function lastSeq(SplFileObject $splFileObject, int $size): int
    {
        $offset = $size;
        $carry = '';
        $seq = 0;

        while ($offset > 0 && $seq === 0) {
            $length = min(self::BLOCK, $offset);
            $offset -= $length;
            $splFileObject->fseek($offset);
            $lines = explode("\n", $splFileObject->fread($length) . $carry);
            $carry = $offset > 0
                ? array_shift($lines)
                : '';
            $seq = $this->seqIn($lines);
        }

        return $seq;
    }

    /**
     * @param list<string> $lines Whole lines, in file order.
     *
     * @return int The last one's `seq` that parses; 0 for none.
     */
    private function seqIn(array $lines): int
    {
        for ($index = count($lines) - 1; $index >= 0; $index -= 1) {
            $decoded = json_decode(trim($lines[$index]), true);
            $seq = is_array($decoded)
                ? $decoded['seq'] ?? null
                : null;

            if (is_int($seq)) {
                return $seq;
            }
        }

        return 0;
    }

    /**
     * @param EventType $eventType The event.
     * @param string    $why       Why it was not written.
     *
     * @return string The note.
     */
    private function note(EventType $eventType, string $why): string
    {
        return 'the run-event log var/workflow/events.jsonl did not take ' . $eventType->value . ' (' . $why . '); the run goes on.';
    }
}
