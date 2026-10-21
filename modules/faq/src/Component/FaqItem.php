<?php

declare(strict_types=1);

namespace App\Faq\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use AIC\Component\Domain\RendersMarkup;
use App\Faq\Entity\FaqEntry;
use Override;

/**
 * One question and its answer, as a term and its description. The module
 * exports it, so another module may show a question too.
 */
final readonly class FaqItem implements Component
{
    use RendersMarkup;

    /**
     * @param FaqEntry $faqEntry The question and its answer.
     */
    public function __construct(
        private FaqEntry $faqEntry,
    ) {
    }

    /**
     * @return Html `<dt>` and `<dd>`, escaped.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<dt>' . self::text($this->faqEntry->question) . '</dt><dd>' . self::text($this->faqEntry->answer) . '</dd>');
    }
}
