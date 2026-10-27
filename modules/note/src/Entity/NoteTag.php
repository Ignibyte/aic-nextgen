<?php

declare(strict_types=1);

namespace App\Note\Entity;

/**
 * One tag on a note, with the note's title, as the tags page lists it (#190).
 */
final readonly class NoteTag
{
    /**
     * @param string $tag       The tag.
     * @param int    $noteId    The note's key.
     * @param string $noteTitle The note's title, read through the join.
     */
    public function __construct(
        public string $tag,
        public int $noteId,
        public string $noteTitle,
    ) {
    }

    /**
     * @return string It in one line, for a person.
     */
    public function line(): string
    {
        return $this->tag . ': ' . $this->noteTitle . ' (#' . $this->noteId . ')';
    }
}
