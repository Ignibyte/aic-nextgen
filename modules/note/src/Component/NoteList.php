<?php

declare(strict_types=1);

namespace App\Note\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use AIC\Component\Domain\Badge;
use AIC\Component\Domain\KeyedComponent;
use Override;

/**
 * The list of notes, each linking to its page, under a heading with their
 * count; from NoteList.html.php.
 */
final readonly class NoteList implements Component
{
    /**
     * @param Badge                $count The count of the notes, as a badge.
     * @param list<KeyedComponent> $links A link to each note's page, keyed by its id, in order.
     */
    public function __construct(
        public Badge $count,
        public array $links,
    ) {
    }

    /**
     * @return Html The heading and the list, or a line saying there are none.
     */
    #[Override]
    public function render(): Html
    {
        return (new NoteListTemplate())->render($this);
    }
}
