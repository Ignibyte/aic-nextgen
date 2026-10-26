<?php

declare(strict_types=1);

namespace App\Theme\Components\NoteList;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Badge;
use AIC\Component\Domain\KeyedComponent;

/**
 * The list of notes, each linking to its page, under a heading with their
 * count; from note-list.phtml.
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
}
