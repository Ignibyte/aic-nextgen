<?php

declare(strict_types=1);

namespace App\Note\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use App\Note\Entity\Note;
use App\Note\Entity\NoteTypes;
use InvalidArgumentException;

use function array_map;

/**
 * The notes: the `notes` entity type over the entity store, its rows mapped to
 * Notes.
 */
final readonly class NoteRepository
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param NoteTypes   $noteTypes   The module's entity types, `notes` among them.
     */
    public function __construct(
        private EntityStore $entityStore,
        private NoteTypes $noteTypes,
    ) {
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return list<Note> Every note, oldest first.
     */
    public function all(): array
    {
        return array_map(self::note(...), $this->entityStore->query($this->noteTypes->notes())->orderBy('id')->rows());
    }

    /**
     * @param int $id The note's key.
     *
     * @throws EntityNotFoundException  When there is no such note.
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return Note The note.
     */
    public function find(int $id): Note
    {
        return self::note($this->entityStore->query($this->noteTypes->notes())->where('id', $id)->one());
    }

    /**
     * @param string $title The title.
     * @param string $body  The body; empty for none.
     *
     * @throws QueryException           When the insert fails.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return int The new note's key.
     */
    public function create(string $title, string $body): int
    {
        return $this->entityStore->insert($this->noteTypes->notes(), ['title' => $title, 'body' => $body]);
    }

    /**
     * @param int    $id    The note's key.
     * @param string $title The new title.
     * @param string $body  The new body.
     *
     * @throws EntityNotFoundException  When there is no such note.
     * @throws QueryException           When the update fails.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     */
    public function update(int $id, string $title, string $body): void
    {
        $this->entityStore->update($this->noteTypes->notes(), $id, ['title' => $title, 'body' => $body]);
    }

    /**
     * @param int $id The note's key.
     *
     * @throws EntityNotFoundException When there is no such note.
     * @throws QueryException          When the delete fails.
     */
    public function delete(int $id): void
    {
        $this->entityStore->delete($this->noteTypes->notes(), $id);
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
