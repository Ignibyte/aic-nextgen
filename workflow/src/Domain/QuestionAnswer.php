<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One answered question in `run.json`'s `qa_history` (#117): what was asked,
 * by whom, about which tree, and what the operator answered from a terminal.
 * The history is appended to, never rewritten.
 */
final readonly class QuestionAnswer
{
    /**
     * @param string $kind       `question`, `conversation` or `complete`.
     * @param string $phase      The phase it was asked at.
     * @param string $question   What was asked.
     * @param string $askedBy    `engine` or `agent`.
     * @param string $askedAt    When it was asked, UTC.
     * @param string $key        The progress key of the tree it was asked about.
     * @param string $option     `advance` or `stay` for a phase exit; empty for an agent's question.
     * @param string $answer     The operator's words.
     * @param string $answeredAt When it was answered, UTC.
     */
    public function __construct(
        public string $kind,
        public string $phase,
        public string $question,
        public string $askedBy,
        public string $askedAt,
        public string $key,
        public string $option,
        public string $answer,
        public string $answeredAt,
    ) {
    }

    /**
     * @return array<string, string> Its `run.json` and event form; `via` is always `terminal`.
     */
    public function toArray(): array
    {
        return [
            'answer' => $this->answer,
            'answered_at' => $this->answeredAt,
            'asked_at' => $this->askedAt,
            'asked_by' => $this->askedBy,
            'key' => $this->key,
            'kind' => $this->kind,
            'option' => $this->option,
            'phase' => $this->phase,
            'question' => $this->question,
            'via' => 'terminal',
        ];
    }
}
