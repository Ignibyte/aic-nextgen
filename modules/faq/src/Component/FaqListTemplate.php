<?php

declare(strict_types=1);

namespace App\Faq\Component;

use AIC\Component\Domain\RendersMarkup;
use App\Faq\Entity\FaqEntry;

/**
 * FaqList's markup, compiled from FaqList.html.php by bin/aic-build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class FaqListTemplate
{
    use RendersMarkup;

    /**
     * @param FaqList $faqList The component.
     *
     * @return string Its markup.
     */
    public function render(FaqList $faqList): string
    {
        return $this->part2($faqList);
    }

    /**
     * @param FaqEntry $faqEntry A loop's variable.
     *
     * @return string Markup from FaqList.html.php.
     */
    private function part0(FaqEntry $faqEntry): string
    {
        return '<dt>'
            . self::text($faqEntry->question)
            . '</dt><dd>'
            . self::text($faqEntry->answer)
            . '</dd>';
    }

    /**
     * @param FaqList $faqList The component.
     *
     * @return string Markup from FaqList.html.php.
     */
    private function part1(FaqList $faqList): string
    {
        $markup = '';

        foreach ($faqList->entries as $faqEntry) {
            $markup .= $this->part0($faqEntry);
        }

        return $markup;
    }

    /**
     * @param FaqList $faqList The component.
     *
     * @return string Markup from FaqList.html.php.
     */
    private function part2(FaqList $faqList): string
    {
        return '<section id="faq"><h2>Questions about modules</h2><dl>'
            . '<!--t:b-->'
            . $this->part1($faqList)
            . '<!--/t:b-->'
            . '</dl></section>';
    }
}
