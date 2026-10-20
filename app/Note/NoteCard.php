<?php

declare(strict_types=1);

namespace App\Note;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A note's article: its title, body and date, from NoteCard.tpl.
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

    /**
     * @return Html `<article id="note">`.
     */
    #[Override]
    public function render(): Html
    {
        return (new NoteCardTemplate())->render($this);
    }
}
