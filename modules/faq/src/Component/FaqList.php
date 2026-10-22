<?php

declare(strict_types=1);

namespace App\Faq\Component;

use AIC\Component\Contracts\Component;
use App\Faq\Entity\FaqEntry;

/**
 * The FAQ, from FaqList.html.php: a heading and a description list with each
 * entry's question and answer.
 */
final readonly class FaqList implements Component
{
    /**
     * @param list<FaqEntry> $entries The questions, in order.
     */
    public function __construct(
        public array $entries,
    ) {
    }
}
