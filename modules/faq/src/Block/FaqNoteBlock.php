<?php

declare(strict_types=1);

namespace App\Faq\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Paragraph;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A line under the FAQ saying where its wiring came from: `content`, after
 * the page's own components, on `/faq` only.
 */
final readonly class FaqNoteBlock implements Block
{
    /**
     * @return Placement `content`, weight 10, under `/faq`.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('content', 10, '/faq');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request; the line is the same on every page.
     *
     * @return Component `<p id="faq-note">`.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        return new Paragraph('This page, its route and this block were wired from modules/faq/faq.module.php.', 'faq-note');
    }
}
