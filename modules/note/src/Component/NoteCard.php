<?php

declare(strict_types=1);

namespace App\Note\Component;

use AIC\Component\Contracts\Component;
use App\Note\Entity\Note;

/**
 * A note's article: its title, body and date, from note-card.tpl.php.
 */
final readonly class NoteCard implements Component
{
    /**
     * @param Note $note The note.
     */
    public function __construct(
        public Note $note,
    ) {
    }
}
