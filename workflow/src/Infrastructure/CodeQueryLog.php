<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\CodeQueryLedger;
use AIC\Workflow\Domain\CodeQuery;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;
use RuntimeException;
use SplFileObject;
use stdClass;

use function in_array;
use function is_file;
use function json_decode;
use function rtrim;

/**
 * The run's lookups from `var/workflow/events.jsonl` (#118, after Droost's
 * `ToolCallLog`): the `code.queried` events of one run at one phase, read
 * line by line, a line that does not parse skipped, every value narrowed by
 * `JsonReader`.
 */
final readonly class CodeQueryLog implements CodeQueryLedger
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param string $runId The run.
     * @param Phase  $phase The phase.
     *
     * @throws WorkflowRefusal When the log is there and cannot be opened.
     *
     * @return list<CodeQuery> Its lookups at the phase, oldest first.
     */
    #[Override]
    public function queries(string $runId, Phase $phase): array
    {
        $path = $this->root . '/' . RunStateFile::DIRECTORY . '/events.jsonl';

        if (!is_file($path)) {
            return [];
        }

        try {
            $splFileObject = new SplFileObject($path, 'r');
        } catch (RuntimeException $runtimeException) {
            throw new WorkflowRefusal('the event log could not be read: ' . $runtimeException->getMessage(), 0, $runtimeException);
        }

        $queries = [];

        while (!$splFileObject->eof()) {
            $codeQuery = $this->query($splFileObject->fgets(), $runId, $phase);

            if (!($codeQuery instanceof CodeQuery)) {
                continue;
            }

            $queries[] = $codeQuery;
        }

        return $queries;
    }

    /**
     * @param string $line  One line of the log.
     * @param string $runId The run.
     * @param Phase  $phase The phase.
     *
     * @return CodeQuery|null The lookup it records, when it is this run's `code.queried` at the phase; null otherwise.
     */
    private function query(string $line, string $runId, Phase $phase): ?CodeQuery
    {
        $event = [json_decode(rtrim($line, "\n"))];

        try {
            $payload = $event[0] instanceof stdClass
                ? $this->payload(new JsonReader($event[0], 'events.jsonl'), $runId)
                : null;

            return $payload === null || $payload->string('phase') !== $phase->value ? null : new CodeQuery(
                $payload->string('command'),
                $payload->string('target'),
                $payload->string('outcome') === 'ok',
                $payload->strings('files'),
                $payload->strings('scopes'),
                in_array('truncated', $payload->keys(), true) && $payload->bool('truncated'),
            );
        } catch (WorkflowRefusal) {
            return null;
        }
    }

    /**
     * @param JsonReader $jsonReader One event.
     * @param string     $runId      The run.
     *
     * @throws WorkflowRefusal When it is not an event's shape.
     *
     * @return JsonReader|null Its payload when it is this run's `code.queried`; null otherwise.
     */
    private function payload(JsonReader $jsonReader, string $runId): ?JsonReader
    {
        return $jsonReader->string('type') === 'code.queried' && $jsonReader->string('run_id') === $runId
            ? $jsonReader->object('payload')
            : null;
    }
}
