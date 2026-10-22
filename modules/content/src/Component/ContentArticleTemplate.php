<?php

declare(strict_types=1);

namespace App\Content\Component;

use AIC\Component\Domain\RendersMarkup;

/**
 * ContentArticle's markup, compiled from ContentArticle.html.php by bin/aic-build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ContentArticleTemplate
{
    use RendersMarkup;

    /**
     * @param ContentArticle $contentArticle The component.
     *
     * @return string Its markup.
     */
    public function render(ContentArticle $contentArticle): string
    {
        return $this->part2($contentArticle);
    }

    /**
     * @param string $paragraph A loop's variable.
     *
     * @return string Markup from ContentArticle.html.php.
     */
    private function part0(string $paragraph): string
    {
        return '<p>'
            . self::text($paragraph)
            . "</p>\n";
    }

    /**
     * @param ContentArticle $contentArticle The component.
     *
     * @return string Markup from ContentArticle.html.php.
     */
    private function part1(ContentArticle $contentArticle): string
    {
        $markup = '';

        foreach ($contentArticle->paragraphs as $paragraph) {
            $markup .= $this->part0($paragraph);
        }

        return $markup;
    }

    /**
     * @param ContentArticle $contentArticle The component.
     *
     * @return string Markup from ContentArticle.html.php.
     */
    private function part2(ContentArticle $contentArticle): string
    {
        return '<article id="content-page"><h2 id="content-title">'
            . self::text($contentArticle->title)
            . "</h2>\n"
            . '<!--t:b-->'
            . $this->part1($contentArticle)
            . '<!--/t:b-->'
            . "</article>\n";
    }
}
