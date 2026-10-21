<?php

declare(strict_types=1);

namespace App\Faq\Entity;

use JsonSerializable;
use Override;

/**
 * One question and its answer; as JSON, `{"question": …, "answer": …}`.
 */
final readonly class FaqEntry implements JsonSerializable
{
    /**
     * @param string $question The question.
     * @param string $answer   The answer, plain text.
     */
    public function __construct(
        public string $question,
        public string $answer,
    ) {
    }

    /**
     * @return array<string, string> The question and the answer.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['question' => $this->question, 'answer' => $this->answer];
    }
}
