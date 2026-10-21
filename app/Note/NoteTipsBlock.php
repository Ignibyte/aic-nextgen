<?php

declare(strict_types=1);

namespace App\Note;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A hint about notes, in `nav` on the note pages, before the powered-by line.
 */
final readonly class NoteTipsBlock implements Block
{
    /**
     * @return Placement `nav`, weight 10, under `/notes`.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('nav', 10, '/notes');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request; the line is the same on every page.
     *
     * @return Component `<p id="note-tips">`.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        return new Html('<p id="note-tips">A note keeps a title and a body; edit or delete it from its page.</p>');
    }
}
