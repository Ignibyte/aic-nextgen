<?php

declare(strict_types=1);

namespace App\Theme\Components\NoteCard;

use AIC\Component\Contracts\Component;

/**
 * A note's article: its title, body and date, from note-card.tpl.php.
 */
final readonly class NoteCard implements Component
{
    /**
     * @param string $title     The note's title.
     * @param string $body      Its text.
     * @param string $createdAt When it was written.
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $createdAt,
    ) {
    }
}
