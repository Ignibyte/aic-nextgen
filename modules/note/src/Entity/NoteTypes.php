<?php

declare(strict_types=1);

namespace App\Note\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The note module's entity types: `notes`, and `note_tags`, whose `note_id`
 * references a note (#190), so a query on tags can join the note they tag.
 */
final readonly class NoteTypes
{
    /**
     * The `notes` table: a title, a body and when it was written.
     */
    private EntityType $notes;

    /**
     * The `note_tags` table: a note's key and a tag, unique together.
     */
    private EntityType $noteTags;

    /**
     * @throws InvalidArgumentException When a type is declared wrongly.
     */
    public function __construct()
    {
        $this->notes = new EntityType('notes', 'id', [
            new Field('title', FieldType::Text),
            new Field('body', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ]);
        $this->noteTags = new EntityType('note_tags', 'id', [
            new Field('note_id', FieldType::Reference, false, $this->notes),
            new Field('tag', FieldType::Text),
        ]);
    }

    /**
     * @return EntityType The `notes` table.
     */
    public function notes(): EntityType
    {
        return $this->notes;
    }

    /**
     * @return EntityType The `note_tags` table, its `note_id` a reference to a note.
     */
    public function noteTags(): EntityType
    {
        return $this->noteTags;
    }
}
