<?php

declare(strict_types=1);

namespace App\Faq\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use App\Faq\Entity\FaqEntry;
use Override;

/**
 * The FAQ: a heading and a description list with one `FaqItem` per entry.
 */
final readonly class FaqList implements Component
{
    /**
     * @param list<FaqEntry> $entries The questions, in order.
     */
    public function __construct(
        private array $entries,
    ) {
    }

    /**
     * @return Html `<section id="faq">` with its heading and list.
     */
    #[Override]
    public function render(): Html
    {
        $items = '';

        foreach ($this->entries as $entry) {
            $items .= (new FaqItem($entry))->render()->markup();
        }

        return new Html('<section id="faq"><h2>Questions about modules</h2><dl>' . $items . '</dl></section>');
    }
}
