<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\Guard\Hold;
use AIC\Workflow\Domain\Mode;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\QuestionAnswer;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_map;
use function implode;
use function in_array;
use function json_encode;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The questions a run waits on (#117): a phase exit the operator confirms
 * (paused by the engine), and a question the agent asks with
 * `workflow:ask`. The run is saved before the event is logged; only an
 * operator's terminal answers, and every answer is appended to `qa_history`.
 */
final readonly class QuestionHold
{
    /**
     * A phase exit's answers.
     */
    private const array EXIT_OPTIONS = ['advance', 'stay'];

    /**
     * @param RunStore $runStore Loads and saves the run.
     * @param EventLog $eventLog The run's event log.
     * @param RunHost  $runHost  Whether the caller is the operator's terminal.
     * @param UtcTime  $utcTime  The clock.
     */
    public function __construct(
        private RunStore $runStore,
        private EventLog $eventLog,
        private RunHost $runHost,
        private UtcTime $utcTime,
    ) {
    }

    /**
     * @param RunState    $runState    The run, its attempt recorded, held only by the confirmation.
     * @param PhaseReport $phaseReport The attempt, stamped with its key.
     *
     * @throws WorkflowRefusal When the run cannot be saved.
     *
     * @return array{RunState, list<string>} The run, paused and saved, and a note when the event could not be logged.
     */
    public function pause(RunState $runState, PhaseReport $phaseReport): array
    {
        $phase = $runState->currentPhase ?? Phase::Complete;
        $kind = $phase === Phase::Complete && $runState->runStart->mode === Mode::Agentic
            ? 'complete'
            : 'conversation';
        $question = implode(' ', array_map(
            static fn (CheckRow $checkRow): string => $checkRow->why,
            array_filter($phaseReport->rows, static fn (CheckRow $checkRow): bool => $checkRow->checkStatus === CheckStatus::Asked),
        ));
        $now = $this->utcTime->now();
        $hold = new Hold($kind, $phase->value, $question, $now, $question, self::EXIT_OPTIONS, 'engine', $phaseReport->key);

        return $this->wait($runState, $hold);
    }

    /**
     * @param string $question The agent's question.
     *
     * @throws WorkflowRefusal When no run is open, it ended, it already waits, the question is empty, or the run cannot
     *                         be saved.
     *
     * @return list<string> What the agent does next, and a note when the event could not be logged.
     */
    public function ask(string $question): array
    {
        $runState = $this->runStore->load();
        $question = trim($question);

        if (!$runState instanceof RunState || !$runState->currentPhase instanceof Phase) {
            throw new WorkflowRefusal('no run is open at a phase; there is nobody to ask for.');
        }

        if ($runState->awaiting instanceof Hold) {
            throw new WorkflowRefusal('the run already waits on the operator at ' . $runState->awaiting->phase . ': '
                . $runState->awaiting->why);
        }

        if ($question === '') {
            throw new WorkflowRefusal('the question is empty: bin/aic workflow:ask "<question>".');
        }

        $key = $runState->reports[$runState->currentPhase->value]->key ?? '';
        $hold = new Hold('question', $runState->currentPhase->value, $question, $this->utcTime->now(), $question, [], 'agent', $key);
        [, $notes] = $this->wait($runState, $hold);

        return [
            'asked: ' . $question,
            'End your turn now. The operator answers from a terminal; the answer is the last qa_history entry in bin/aic workflow:status.',
            ...$notes,
        ];
    }

    /**
     * @param string $first The first word: `advance` or `stay` for a phase exit; the answer's start for a question.
     * @param string $rest  The rest of the answer: the note, or the answer's remainder.
     *
     * @throws WorkflowRefusal When the caller is no terminal, nothing waits for an answer, the answer does not fit,
     *                         or the run cannot be saved.
     *
     * @return array{bool, list<string>} Whether the operator chose `advance`, and what was recorded.
     */
    public function answer(string $first, string $rest): array
    {
        if (!$this->runHost->operatorTerminal()) {
            throw new WorkflowRefusal("workflow:answer is the operator's: run it from a terminal, not an agent's shell.");
        }

        [$runState, $hold] = $this->waiting();
        [$option, $text] = self::reply($hold, $first, $rest);
        $questionAnswer = $this->entry($hold, $option, $text);
        $answered = $runState->answered($questionAnswer);
        $this->runStore->save($answered);
        $note = $this->eventLog->append($answered, EventType::QuestionAnswered, self::json($questionAnswer->toArray()));
        $said = $option === ''
            ? ''
            : ': ' . $option;

        return [$option === 'advance', ['answered ' . $hold->kind . ' at ' . $hold->phase . $said . '.', ...($note === '' ? [] : [$note])]];
    }

    /**
     * @param Hold   $hold   The question.
     * @param string $option The option chosen; empty for a question.
     * @param string $text   The answer's words.
     *
     * @return QuestionAnswer The `qa_history` entry.
     */
    private function entry(Hold $hold, string $option, string $text): QuestionAnswer
    {
        return new QuestionAnswer(
            $hold->kind,
            $hold->phase,
            $hold->question,
            $hold->askedBy,
            $hold->time,
            $hold->key,
            $option,
            $text,
            $this->utcTime->now(),
        );
    }

    /**
     * @throws WorkflowRefusal When no run is open, or it waits on no question.
     *
     * @return array{RunState, Hold} The open run and the question it waits on.
     */
    private function waiting(): array
    {
        $runState = $this->runStore->load();
        $hold = $runState instanceof RunState
            ? $runState->awaiting
            : null;

        if ($runState instanceof RunState && $hold instanceof Hold && $hold->isQuestion()) {
            return [$runState, $hold];
        }

        throw new WorkflowRefusal($hold instanceof Hold
            ? 'the run waits on the operator for its held rows, which bin/aic workflow:run from a terminal resumes.'
            : 'the run waits on no question; there is nothing to answer.');
    }

    /**
     * @param RunState $runState The run.
     * @param Hold     $hold     What it now waits on.
     *
     * @throws WorkflowRefusal When the run cannot be saved.
     *
     * @return array{RunState, list<string>} The run, waiting and saved, and a note when the event could not be logged.
     */
    private function wait(RunState $runState, Hold $hold): array
    {
        $waiting = $runState->held($hold);
        $this->runStore->save($waiting);
        $note = $this->eventLog->append($waiting, EventType::QuestionAsked, self::json($hold->toArray()));

        return [$waiting, $note === '' ? [] : [$note]];
    }

    /**
     * @param Hold   $hold  The question.
     * @param string $first The first word.
     * @param string $rest  The rest.
     *
     * @throws WorkflowRefusal When a phase exit's first word is neither `advance` nor `stay`, or a question's answer is empty.
     *
     * @return array{string, string} The option (empty for a question) and the answer's words.
     */
    private static function reply(Hold $hold, string $first, string $rest): array
    {
        if (!in_array($hold->kind, Hold::EXITS, true)) {
            $text = trim($first . ' ' . $rest);

            return $text !== ''
                ? ['', $text]
                : throw new WorkflowRefusal('the answer is empty: bin/aic workflow:answer "<answer>".');
        }

        return in_array($first, self::EXIT_OPTIONS, true)
            ? [$first, trim($rest)]
            : throw new WorkflowRefusal('the phase exit takes advance or stay: bin/aic workflow:answer advance|stay "<note>".');
    }

    /**
     * @param array<string, string|list<string>> $payload An event's payload.
     *
     * @return string It as a JSON object.
     */
    private static function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
