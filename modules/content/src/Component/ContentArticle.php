<?php

declare(strict_types=1);

namespace App\Content\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use App\Content\Entity\ContentPage;
use Override;

/**
 * A content page as an article, from ContentArticle.tpl: its title and its
 * paragraphs, escaped.
 */
final readonly class ContentArticle implements Component
{
    /**
     * @param ContentPage $contentPage The page.
     */
    public function __construct(
        public ContentPage $contentPage,
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
