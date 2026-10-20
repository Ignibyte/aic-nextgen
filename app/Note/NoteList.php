<?php

declare(strict_types=1);

namespace App\Note;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

use function count;

/**
 * The list of notes, each linking to its page, under a heading with their
 * count; from NoteList.tpl.
 */
final readonly class NoteList implements Component
{
    /**
     * @param list<Note> $notes The notes, in order.
     */
    public function __construct(
        public array $notes,
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

    /**
     * @return bool Whether there are no notes.
     */
    public function isEmpty(): bool
    {
        return count($this->notes) === 0;
    }

    /**
     * @return string How many notes there are.
     */
    public function size(): string
    {
        return (string) count($this->notes);
    }
}
