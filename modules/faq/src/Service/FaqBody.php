<?php

declare(strict_types=1);

namespace App\Faq\Controller;

use App\Faq\Entity\FaqEntry;
use JsonSerializable;
use Override;

/**
 * The body of GET /api/faq: the questions, each with its answer.
 */
final readonly class FaqBody implements JsonSerializable
{
    /**
     * @param list<FaqEntry> $entries The questions, in order.
     */
    public function __construct(
        private array $entries,
    ) {
    }

    /**
     * Each entry serializes itself: building the rows here, with a property
     * read on each loop element inside an array literal, compiles natively to
     * zeros (#111, an elephc carry).
     *
     * @return array<string, list<FaqEntry>> `{"entries": [{"question": …, "answer": …}, …]}`.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['entries' => $this->entries];
    }
}
