<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

use function in_array;

/**
 * What a run waits on (#102, #117): `run.json`'s `awaiting`. An `operator`
 * hold is a phase only the operator can clear; a `question` is one the agent
 * put to the operator; `conversation` and `complete` are a phase exit
 * waiting for the operator's confirmation, in interactive mode and at a
 * human-confirmed complete. Only an operator's terminal clears any of them.
 */
final readonly class Hold
{
    /**
     * The kinds that wait for an answer from `workflow:answer`.
     */
    public const array QUESTIONS = ['question', 'conversation', 'complete'];

    /**
     * The kinds whose answer is `advance` or `stay`.
     */
    public const array EXITS = ['conversation', 'complete'];

    /**
     * @param string       $kind     `operator`, `question`, `conversation` or `complete`.
     * @param string       $phase    The phase that is held.
     * @param string       $why      What holds it: the holding rows, or the question.
     * @param string       $time     When it began, UTC.
     * @param string       $question What the operator is asked; empty for an operator hold.
     * @param list<string> $options  The answers a phase exit takes (`advance`, `stay`); empty otherwise.
     * @param string       $askedBy  `engine` or `agent`; empty for an operator hold.
     * @param string       $key      The progress key of the tree it was asked about; empty for an operator hold.
     */
    public function __construct(
        public string $kind,
        public string $phase,
        public string $why,
        public string $time,
        public string $question = '',
        public array $options = [],
        public string $askedBy = '',
        public string $key = '',
    ) {
    }

    /**
     * @return bool Whether it waits for an answer, not for the operator's `workflow:run`.
     */
    public function isQuestion(): bool
    {
        return in_array($this->kind, self::QUESTIONS, true);
    }

    /**
     * @return array<string, string|list<string>> Its `run.json` and event form; a question's adds what was asked.
     */
    public function toArray(): array
    {
        $base = ['at' => $this->time, 'kind' => $this->kind, 'phase' => $this->phase, 'why' => $this->why];

        return $this->isQuestion()
            ? [...$base, 'asked_by' => $this->askedBy, 'key' => $this->key, 'options' => $this->options, 'question' => $this->question]
            : $base;
    }
}
