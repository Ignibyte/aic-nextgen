<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\Phases;
use AIC\Workflow\Domain\PhaseStatus;
use AIC\Workflow\Domain\RunNote;
use AIC\Workflow\Domain\RunPhases;
use AIC\Workflow\Domain\RunStart;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;
use stdClass;

use function array_map;
use function json_decode;
use function json_encode;
use function ksort;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `run.json`, version 1 (#100): written whole, read strictly. A missing `v`,
 * another version, a wrong type or an unknown key is refused by name; later
 * parts add keys, each read as empty when absent.
 */
final readonly class RunStateJson
{
    /**
     * The version this reader writes and reads.
     */
    private const int VERSION = 1;

    /**
     * The keys version 1 holds.
     */
    private const array KEYS = [
        'v',
        'run_id',
        'started_at',
        'ticket',
        'base_commit',
        'spec_path',
        'phases',
        'current_phase',
        'attempts',
        'reports',
        'notes',
        'enforcement',
        'awaiting',
        'rule_files',
        'mode',
        'human_complete',
        'qa_history',
    ];

    /**
     * @param RunPartsJson $runPartsJson Reads the ticket, the reports and the notes.
     * @param QuestionJson $questionJson Reads the hold, the mode and the answered questions (#117).
     */
    public function __construct(
        private RunPartsJson $runPartsJson = new RunPartsJson(),
        private QuestionJson $questionJson = new QuestionJson(),
    ) {
    }

    /**
     * @param RunState $runState A run.
     *
     * @return string Its `run.json`, pretty JSON with a closing line break.
     */
    public function encode(RunState $runState): string
    {
        $document = [
            'attempts' => $runState->attempts === [] ? new stdClass() : $runState->attempts,
            'base_commit' => $runState->runStart->baseCommit,
            'current_phase' => $runState->currentPhase instanceof Phase ? $runState->currentPhase->value : null,
            'enforcement' => $runState->runStart->enforcement->value,
            'notes' => array_map(static fn (RunNote $runNote): array => $runNote->toArray(), $runState->notes),
            'phases' => $runState->runPhases->names(),
            'reports' => $runState->reports === []
                ? new stdClass()
                : array_map(static fn (PhaseReport $phaseReport): array => $phaseReport->toArray(), $runState->reports),
            'rule_files' => $runState->runStart->ruleFiles === [] ? new stdClass() : $runState->runStart->ruleFiles,
            'run_id' => $runState->runStart->runId,
            'spec_path' => $runState->runStart->specPath,
            'started_at' => $runState->runStart->startedAt,
            'ticket' => $runState->ticket->toArray(),
            'v' => self::VERSION,
            ...$this->questionJson->encode($runState),
        ];
        ksort($document);

        return json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * @param string $json A `run.json`.
     * @param string $what Which file, for refusals.
     *
     * @throws WorkflowRefusal When it is not version 1's shape, by name.
     *
     * @return RunState The run.
     */
    public function decode(string $json, string $what): RunState
    {
        try {
            $decoded = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            $why = $jsonException->getMessage();

            throw new WorkflowRefusal($what . ' is not JSON (' . $why . '); it was left as it is.', 0, $jsonException);
        }

        if (!$decoded instanceof stdClass) {
            throw new WorkflowRefusal($what . ' is not a JSON object; it was left as it is.');
        }

        $jsonReader = new JsonReader($decoded, $what);

        if ($jsonReader->int('v') !== self::VERSION) {
            throw new WorkflowRefusal($what . ' is version ' . $jsonReader->int('v') . ', not 1; it was left as it is.');
        }

        $jsonReader->only(self::KEYS);

        return $this->run($jsonReader);
    }

    /**
     * @param JsonReader $jsonReader The document, its version and keys checked.
     *
     * @throws WorkflowRefusal When a value has the wrong type.
     *
     * @return RunState The run.
     */
    private function run(JsonReader $jsonReader): RunState
    {
        $currentPhase = $jsonReader->nullableString('current_phase');

        return new RunState(
            $this->start($jsonReader),
            $this->runPartsJson->ticket($jsonReader->object('ticket')),
            $this->phases($jsonReader->object('phases')),
            $currentPhase === null ? null : $this->phase($currentPhase),
            $this->runPartsJson->attempts($jsonReader->object('attempts')),
            $this->runPartsJson->reports($jsonReader->object('reports')),
            $this->runPartsJson->notes($jsonReader->objects('notes')),
            $this->questionJson->hold($jsonReader),
            $this->questionJson->history($jsonReader),
        );
    }

    /**
     * @param JsonReader $jsonReader The document.
     *
     * @throws WorkflowRefusal When a value has the wrong type.
     *
     * @return RunStart What the run is from its start.
     */
    private function start(JsonReader $jsonReader): RunStart
    {
        return new RunStart(
            $jsonReader->string('run_id'),
            $jsonReader->string('started_at'),
            $jsonReader->nullableString('base_commit'),
            $jsonReader->string('spec_path'),
            $this->runPartsJson->enforcement($jsonReader),
            $this->runPartsJson->ruleFiles($jsonReader),
            $this->questionJson->mode($jsonReader),
            $this->questionJson->humanComplete($jsonReader),
        );
    }

    /**
     * @param JsonReader $jsonReader The `phases` object.
     *
     * @throws WorkflowRefusal When it names an unknown phase or status.
     *
     * @return RunPhases The phases.
     */
    private function phases(JsonReader $jsonReader): RunPhases
    {
        $jsonReader->only(['plan', 'code', 'check', 'complete']);
        $statuses = [];

        foreach ((new Phases())->all() as $phase) {
            $statuses[$phase->value] = match ($jsonReader->string($phase->value)) {
                'pending' => PhaseStatus::Pending,
                'active' => PhaseStatus::Active,
                'passed' => PhaseStatus::Passed,
                default => throw new WorkflowRefusal("the run's phase " . $phase->value . ' has an unknown status.'),
            };
        }

        return new RunPhases($statuses);
    }

    /**
     * @param string $name A phase's name.
     *
     * @throws WorkflowRefusal When it is no phase.
     *
     * @return Phase The phase.
     */
    private function phase(string $name): Phase
    {
        return (new Phases())->named($name) ?? throw new WorkflowRefusal('the run names an unknown phase "' . $name . '".');
    }
}
