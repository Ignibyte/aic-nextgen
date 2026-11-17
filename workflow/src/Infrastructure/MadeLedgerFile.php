<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Make\MadeLedger;
use AIC\Workflow\Domain\Make\MadeRow;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;
use RuntimeException;
use SplFileObject;
use stdClass;

use function array_filter;
use function array_map;
use function array_values;
use function dirname;
use function explode;
use function file_get_contents;
use function implode;
use function is_file;
use function json_decode;
use function rtrim;
use function strlen;

use const LOCK_EX;
use const LOCK_UN;
use const SEEK_END;

/**
 * `.aic/made.jsonl` (#302): rows appended under an exclusive lock, read back
 * as lines; and the rows a run's `code.made` events in
 * `var/workflow/events.jsonl` give, read like `CodeQueryLog`, a line that
 * does not parse skipped (an event from before #302 carries no kind and
 * gives none).
 */
final readonly class MadeLedgerFile implements MadeLedger
{
    /**
     * @param string     $root       The project's root.
     * @param AtomicFile $atomicFile Makes the directory.
     */
    public function __construct(
        private string $root,
        private AtomicFile $atomicFile = new AtomicFile(),
    ) {
    }

    /**
     * @param list<MadeRow> $rows What a make created.
     *
     * @return string Empty when appended; a note otherwise.
     */
    #[Override]
    public function append(array $rows): string
    {
        $path = $this->root . '/' . MadeLedger::PATH;
        $text = implode('', array_map(static fn (MadeRow $madeRow): string => $madeRow->line() . "\n", $rows));

        try {
            $this->atomicFile->directory(dirname($path));
        } catch (WorkflowRefusal $workflowRefusal) {
            return self::note($workflowRefusal->getMessage());
        }

        try {
            $splFileObject = new SplFileObject($path, 'a');
        } catch (RuntimeException $runtimeException) {
            return self::note($runtimeException->getMessage());
        }

        if (!$splFileObject->flock(LOCK_EX)) {
            return self::note('it could not be locked');
        }

        $written = $splFileObject->fseek(0, SEEK_END) === 0 && $splFileObject->fwrite($text) === strlen($text);
        $splFileObject->flock(LOCK_UN);

        return $written
            ? ''
            : self::note('the rows could not be written');
    }

    /**
     * @throws WorkflowRefusal When it is there and cannot be read.
     *
     * @return list<string> Its lines.
     */
    #[Override]
    public function lines(): array
    {
        $path = $this->root . '/' . MadeLedger::PATH;
        $text = is_file($path)
            ? file_get_contents($path)
            : '';

        if ($text === false) {
            throw new WorkflowRefusal(MadeLedger::PATH . ' could not be read.');
        }

        return array_values(array_filter(explode("\n", $text), static fn (string $line): bool => $line !== ''));
    }

    /**
     * @throws WorkflowRefusal When it is there and cannot be read.
     *
     * @return list<MadeRow> Its rows that parse.
     */
    #[Override]
    public function rows(): array
    {
        $rows = [];

        foreach ($this->lines() as $line) {
            $row = [json_decode($line)];

            try {
                $rows = $row[0] instanceof stdClass
                    ? [...$rows, self::row(new JsonReader($row[0], MadeLedger::PATH))]
                    : $rows;
            } catch (WorkflowRefusal) {
                continue;
            }
        }

        return $rows;
    }

    /**
     * @param string $runId A run.
     *
     * @throws WorkflowRefusal When the event log is there and cannot be opened.
     *
     * @return list<MadeRow> The rows its `code.made` events give.
     */
    #[Override]
    public function made(string $runId): array
    {
        $path = $this->root . '/' . RunStateFile::DIRECTORY . '/events.jsonl';

        try {
            $splFileObject = is_file($path)
                ? new SplFileObject($path, 'r')
                : null;
        } catch (RuntimeException $runtimeException) {
            throw new WorkflowRefusal('the event log could not be read: ' . $runtimeException->getMessage(), 0, $runtimeException);
        }

        $rows = [];

        while ($splFileObject instanceof SplFileObject && !$splFileObject->eof()) {
            $rows = [...$rows, ...self::eventRows($splFileObject->fgets(), $runId)];
        }

        return $rows;
    }

    /**
     * @param string $line  One line of the log.
     * @param string $runId The run.
     *
     * @return list<MadeRow> The rows it gives, when it is this run's `code.made`; empty otherwise.
     */
    private static function eventRows(string $line, string $runId): array
    {
        $event = [json_decode(rtrim($line, "\n"))];

        try {
            $jsonReader = $event[0] instanceof stdClass
                ? new JsonReader($event[0], 'events.jsonl')
                : null;

            return $jsonReader instanceof JsonReader
                && $jsonReader->string('type') === 'code.made'
                && $jsonReader->string('run_id') === $runId
                ? self::created($jsonReader->object('payload'), $runId)
                : [];
        } catch (WorkflowRefusal) {
            return [];
        }
    }

    /**
     * @param JsonReader $jsonReader A `code.made` payload.
     * @param string     $runId      Its run.
     *
     * @throws WorkflowRefusal When it is not #302's shape.
     *
     * @return list<MadeRow> A row per file it created.
     */
    private static function created(JsonReader $jsonReader, string $runId): array
    {
        $kind = $jsonReader->string('kind');
        $scaffold = $jsonReader->string('scaffold');
        $rows = [];

        foreach ($jsonReader->objects('files') as $file) {
            $rows = $file->bool('created')
                ? [...$rows, new MadeRow($file->string('path'), $kind, $scaffold, $file->string('hash'), $runId)]
                : $rows;
        }

        return $rows;
    }

    /**
     * @param JsonReader $jsonReader A ledger row.
     *
     * @throws WorkflowRefusal When it is not a row's shape.
     *
     * @return MadeRow The row.
     */
    private static function row(JsonReader $jsonReader): MadeRow
    {
        return new MadeRow(
            $jsonReader->string('path'),
            $jsonReader->string('kind'),
            $jsonReader->string('scaffold'),
            $jsonReader->string('content'),
            $jsonReader->string('run'),
        );
    }

    /**
     * @param string $why Why the rows were not appended.
     *
     * @return string The note.
     */
    private static function note(string $why): string
    {
        return 'the made ledger ' . MadeLedger::PATH . " did not take this make's rows (" . $why . ').';
    }
}
