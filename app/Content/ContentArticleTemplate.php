<?php

declare(strict_types=1);

namespace App\Content;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\RendersMarkup;

use function array_map;
use function implode;

/**
 * ContentArticle's markup, compiled from ContentArticle.tpl by bin/aic-build (#36):
 * edit the template and run composer run build, never this file.
 */
final readonly class ContentArticleTemplate
{
    use RendersMarkup;

    /**
     * @param ContentArticle $contentArticle The component.
     *
     * @return Html Its markup.
     */
    public function render(ContentArticle $contentArticle): Html
    {
        return new Html($this->part1($contentArticle));
    }

    /**
     * @param string $paragraph A loop's item.
     *
     * @return string Markup from ContentArticle.tpl.
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
     * @return string Markup from ContentArticle.tpl.
     */
    private function part1(ContentArticle $contentArticle): string
    {
        return '<article id="content-page"><h2 id="content-title">'
            . self::text($contentArticle->contentPage->title)
            . "</h2>\n"
            . '<!--t:b-->'
            . implode('', array_map(
                $this->part0(...),
                $contentArticle->contentPage->paragraphs(),
            ))
            . '<!--/t:b-->'
            . "</article>\n";
    }
}
