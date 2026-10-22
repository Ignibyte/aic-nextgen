<?php

declare(strict_types=1);

namespace App\Content\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A content page as an article, from ContentArticle.html.php: its title and
 * its paragraphs, escaped.
 */
final readonly class ContentArticle implements Component
{
    /**
     * @param string       $title      The page's title.
     * @param list<string> $paragraphs The page's paragraphs, in order.
     */
    public function __construct(
        public string $title,
        public array $paragraphs,
    ) {
    }

    /**
     * @return Html `<article id="content-page">`.
     */
    #[Override]
    public function render(): Html
    {
        return (new ContentArticleTemplate())->render($this);
    }
}
