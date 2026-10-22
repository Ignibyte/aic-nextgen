<?php

declare(strict_types=1);

namespace App\Note\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Note\Entity\Note;

use function array_map;

/**
 * The notes: the `notes` entity type over the entity store, its rows mapped to
 * Notes.
 */
final readonly class NoteRepository
{
    /**
     * The `notes` table: a title, a body and when it was written.
     */
    private EntityType $entityType;

    /**
     * @param EntityStore $entityStore The entity store.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
        $this->entityType = new EntityType('notes', 'id', [
            new Field('title', FieldType::Text),
            new Field('body', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<Note> Every note, oldest first.
     */
    public function all(): array
    {
        return array_map(self::note(...), $this->entityStore->query($this->entityType)->orderBy('id')->rows());
    }

    /**
     * @param int $id The note's key.
     *
     * @throws EntityNotFoundException When there is no such note.
     * @throws QueryException          When the query fails.
     *
     * @return Note The note.
     */
    public function find(int $id): Note
    {
        return self::note($this->entityStore->query($this->entityType)->where('id', $id)->one());
    }

    /**
     * @param string $title The title.
     * @param string $body  The body; empty for none.
     *
     * @throws QueryException When the insert fails.
     *
     * @return int The new note's key.
     */
    public function create(string $title, string $body): int
    {
        return $this->entityStore->insert($this->entityType, ['title' => $title, 'body' => $body]);
    }

    /**
     * @param int    $id    The note's key.
     * @param string $title The new title.
     * @param string $body  The new body.
     *
     * @throws EntityNotFoundException When there is no such note.
     * @throws QueryException          When the update fails.
     */
    public function update(int $id, string $title, string $body): void
    {
        $this->entityStore->update($this->entityType, $id, ['title' => $title, 'body' => $body]);
    }

    /**
     * @param int $id The note's key.
     *
     * @throws EntityNotFoundException When there is no such note.
     * @throws QueryException          When the delete fails.
     */
    public function delete(int $id): void
    {
        $this->entityStore->delete($this->entityType, $id);
    }

    /**
     * @param Row $row A notes row.
     *
     * @throws QueryException When a column is missing or mistyped.
     *
     * @return Note The note.
     */
    private static function note(Row $row): Note
    {
        return new Note($row->int('id'), $row->string('title'), $row->string('body'), $row->string('created_at'));
    }
}
