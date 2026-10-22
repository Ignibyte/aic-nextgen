<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\Guard\Hold;
use AIC\Workflow\Domain\Mode;
use AIC\Workflow\Domain\QuestionAnswer;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function in_array;

/**
 * Reads and writes what a run waits on and what was answered (#117): `awaiting` of every
 * kind, `qa_history`, `mode` and `human_complete`, strictly. A run.json
 * written before #117 has none of the last three and reads as agentic, the
 * operator not confirming complete, with no history.
 */
final readonly class QuestionJson
{
    /**
     * The keys an operator hold holds.
     */
    private const array OPERATOR_KEYS = ['kind', 'phase', 'why', 'at'];

    /**
     * The keys a question's hold holds.
     */
    private const array QUESTION_KEYS = ['kind', 'phase', 'why', 'at', 'asked_by', 'key', 'options', 'question'];

    /**
     * The keys a `qa_history` entry holds.
     */
    private const array ANSWER_KEYS = [
        'answer',
        'answered_at',
        'asked_at',
        'asked_by',
        'key',
        'kind',
        'option',
        'phase',
        'question',
        'via',
    ];

    /**
     * @param JsonReader $jsonReader The run; `awaiting` is absent from a run.json #100 or #101 wrote.
     *
     * @throws WorkflowRefusal When it is not a hold's shape, or names an unknown kind.
     *
     * @return Hold|null What it waits on; null when it is absent or null.
     */
    public function hold(JsonReader $jsonReader): ?Hold
    {
        if (!in_array('awaiting', $jsonReader->keys(), true) || $jsonReader->isNull('awaiting')) {
            return null;
        }

        $awaiting = $jsonReader->object('awaiting');
        $kind = $awaiting->string('kind');

        if ($kind === 'operator') {
            $awaiting->only(self::OPERATOR_KEYS);

            return new Hold('operator', $awaiting->string('phase'), $awaiting->string('why'), $awaiting->string('at'));
        }

        return $this->question($kind, $awaiting);
    }

    /**
     * @param JsonReader $jsonReader The run.
     *
     * @throws WorkflowRefusal When an entry is not an answer's shape.
     *
     * @return list<QuestionAnswer> The answered questions, in order; none when the key is absent.
     */
    public function history(JsonReader $jsonReader): array
    {
        if (!in_array('qa_history', $jsonReader->keys(), true)) {
            return [];
        }

        $history = [];

        foreach ($jsonReader->objects('qa_history') as $entry) {
            $history[] = $this->answer($entry);
        }

        return $history;
    }

    /**
     * @param RunState $runState A run.
     *
     * @return array{
     *     awaiting: array<string, string|list<string>>|null,
     *     human_complete: bool,
     *     mode: string,
     *     qa_history: list<array<string, string>>
     * } Its `run.json` keys for what it waits on, its mode and its answered questions.
     */
    public function encode(RunState $runState): array
    {
        return [
            'awaiting' => $runState->awaiting instanceof Hold ? $runState->awaiting->toArray() : null,
            'human_complete' => $runState->runStart->humanComplete,
            'mode' => $runState->runStart->mode->value,
            'qa_history' => array_map(
                static fn (QuestionAnswer $questionAnswer): array => $questionAnswer->toArray(),
                $runState->qaHistory,
            ),
        ];
    }

    /**
     * @param JsonReader $jsonReader The run.
     *
     * @throws WorkflowRefusal When it is neither agentic nor interactive.
     *
     * @return Mode The run's frozen mode; agentic when absent.
     */
    public function mode(JsonReader $jsonReader): Mode
    {
        if (!in_array('mode', $jsonReader->keys(), true)) {
            return Mode::Agentic;
        }

        return match ($jsonReader->string('mode')) {
            'agentic' => Mode::Agentic,
            'interactive' => Mode::Interactive,
            default => throw new WorkflowRefusal("the run's mode is neither agentic nor interactive."),
        };
    }

    /**
     * @param JsonReader $jsonReader The run.
     *
     * @throws WorkflowRefusal When it is not a boolean.
     *
     * @return bool Whether the operator confirms complete; false when absent.
     */
    public function humanComplete(JsonReader $jsonReader): bool
    {
        return in_array('human_complete', $jsonReader->keys(), true) && $jsonReader->bool('human_complete');
    }

    /**
     * @param string     $kind       The hold's kind, not `operator`.
     * @param JsonReader $jsonReader The `awaiting` object.
     *
     * @throws WorkflowRefusal When the kind is unknown or the object is not a question's shape.
     *
     * @return Hold The question the run waits on.
     */
    private function question(string $kind, JsonReader $jsonReader): Hold
    {
        if (!in_array($kind, Hold::QUESTIONS, true)) {
            throw new WorkflowRefusal('the run waits on an unknown kind of hold, "' . $kind . '".');
        }

        $jsonReader->only(self::QUESTION_KEYS);

        return new Hold(
            $kind,
            $jsonReader->string('phase'),
            $jsonReader->string('why'),
            $jsonReader->string('at'),
            $jsonReader->string('question'),
            $jsonReader->strings('options'),
            $jsonReader->string('asked_by'),
            $jsonReader->string('key'),
        );
    }

    /**
     * @param JsonReader $jsonReader One `qa_history` entry.
     *
     * @throws WorkflowRefusal When it is not an answer's shape, or was not answered from a terminal.
     *
     * @return QuestionAnswer The answer.
     */
    private function answer(JsonReader $jsonReader): QuestionAnswer
    {
        $jsonReader->only(self::ANSWER_KEYS);

        if ($jsonReader->string('via') !== 'terminal') {
            throw new WorkflowRefusal('a qa_history entry was answered other than from a terminal.');
        }

        return new QuestionAnswer(
            $jsonReader->string('kind'),
            $jsonReader->string('phase'),
            $jsonReader->string('question'),
            $jsonReader->string('asked_by'),
            $jsonReader->string('asked_at'),
            $jsonReader->string('key'),
            $jsonReader->string('option'),
            $jsonReader->string('answer'),
            $jsonReader->string('answered_at'),
        );
    }
}
