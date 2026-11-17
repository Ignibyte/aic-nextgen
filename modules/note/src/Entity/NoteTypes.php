<?php

declare(strict_types=1);

namespace App\Note\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use AIC\Entity\Domain\Relation;
use AIC\Entity\Domain\RelationKind;
use InvalidArgumentException;

/**
 * The note module's entity types: `notes`, and `note_tags`, whose `note_id`
 * references a note (#190), so a query on tags can join the note they tag.
 * Since #306 a note relates to its tags (has many), to stock items through
 * `notes_stock_items` (many to many, with an amount) and to its mentions (has
 * many), and a mention to its subject, a note or a stock item (polymorphic).
 * A target is built without relations back, so no type embeds itself.
 */
final readonly class NoteTypes
{
    /**
     * The `notes` table: a title, a body and when it was written, with its relations.
     */
    private EntityType $notes;

    /**
     * The `note_tags` table: a note's key and a tag, unique together.
     */
    private EntityType $noteTags;

    /**
     * The `note_mentions` table: a note's mention of a note or a stock item.
     */
    private EntityType $noteMentions;

    /**
     * The `stock_items` table: the stock repository's, and the relations' target.
     */
    private EntityType $stockItems;

    /**
     * The `notes_stock_items` pivot: a note, a stock item and the link's amount.
     */
    private EntityType $notesStockItems;

    /**
     * @throws InvalidArgumentException When a type is declared wrongly.
     */
    public function __construct()
    {
        $entityType = self::plainNotes([]);
        $this->noteTags = new EntityType('note_tags', 'id', [
            new Field('note_id', FieldType::Reference, false, $entityType),
            new Field('tag', FieldType::Text),
        ]);
        $this->stockItems = self::stock();
        $this->noteMentions = self::mentions($entityType, $this->stockItems);
        $this->notesStockItems = self::pivot();
        $this->notes = self::plainNotes([
            new Relation('tags', RelationKind::HasMany, $this->noteTags, 'note_id'),
            new Relation('stock_items', RelationKind::ManyToMany, $this->stockItems, 'note_id', $this->notesStockItems, 'stock_item_id'),
            new Relation('mentions', RelationKind::HasMany, self::mentions($entityType, $this->stockItems), 'note_id'),
        ]);
    }

    /**
     * @return EntityType The `notes` table, with its tags, stock items and mentions.
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

    /**
     * @return EntityType The `note_mentions` table, with its subject.
     */
    public function noteMentions(): EntityType
    {
        return $this->noteMentions;
    }

    /**
     * @return EntityType The `stock_items` table.
     */
    public function stockItems(): EntityType
    {
        return $this->stockItems;
    }

    /**
     * @return EntityType The `notes_stock_items` pivot.
     */
    public function notesStockItems(): EntityType
    {
        return $this->notesStockItems;
    }

    /**
     * @param list<object> $relations The relations it declares.
     *
     * @throws InvalidArgumentException When a field is declared wrongly.
     *
     * @return EntityType The `notes` table.
     */
    private static function plainNotes(array $relations): EntityType
    {
        return new EntityType('notes', 'id', [
            new Field('title', FieldType::Text),
            new Field('body', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ], relations: $relations);
    }

    /**
     * @param EntityType $notes      The `notes` table, without relations.
     * @param EntityType $stockItems The `stock_items` table.
     *
     * @throws InvalidArgumentException When a field or the relation is declared wrongly.
     *
     * @return EntityType The `note_mentions` table, its subject a note or a stock item.
     */
    private static function mentions(EntityType $notes, EntityType $stockItems): EntityType
    {
        $relation = new Relation('subject', RelationKind::Polymorphic, key: 'subject_id', typeField: 'subject_type', types: [
            'note' => $notes,
            'stock_item' => $stockItems,
        ]);

        return new EntityType('note_mentions', 'id', [
            new Field('note_id', FieldType::Integer),
            new Field('subject_type', FieldType::Choice, choices: ['note', 'stock_item']),
            new Field('subject_id', FieldType::Integer),
            new Field('created_at', FieldType::CreatedAt),
        ], relations: [$relation]);
    }

    /**
     * @throws InvalidArgumentException When a field is declared wrongly.
     *
     * @return EntityType The `stock_items` table, with a date, a numeric, a boolean, a nullable text and integer,
     *                    and an updated-at field (#184).
     */
    private static function stock(): EntityType
    {
        return new EntityType('stock_items', 'id', [
            new Field('name', FieldType::Text),
            new Field('price', FieldType::Numeric),
            new Field('added_on', FieldType::Date),
            new Field('certified', FieldType::Boolean),
            new Field('created_at', FieldType::CreatedAt),
            new Field('sku', FieldType::Text, true),
            new Field('quantity', FieldType::Integer, true),
            new Field('updated_at', FieldType::UpdatedAt, true),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field is declared wrongly.
     *
     * @return EntityType The `notes_stock_items` pivot, made by make:relation, with each link's amount.
     */
    private static function pivot(): EntityType
    {
        return new EntityType('notes_stock_items', 'id', [
            new Field('note_id', FieldType::Integer),
            new Field('stock_item_id', FieldType::Integer),
            new Field('amount', FieldType::Numeric),
        ]);
    }
}
