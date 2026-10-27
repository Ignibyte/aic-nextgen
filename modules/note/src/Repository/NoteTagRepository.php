<?php

declare(strict_types=1);

namespace App\Note\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Note\Entity\NoteTag;
use App\Note\Entity\NoteTypes;
use InvalidArgumentException;

use function array_map;

/**
 * The notes' tags (#190): `note_tags` read with the note each one tags, joined
 * through its `note_id` reference, and a tag added to a note once: adding it again changes nothing.
 */
final readonly class NoteTagRepository
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param NoteTypes   $noteTypes   The module's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private NoteTypes $noteTypes,
    ) {
    }

    /**
     * @param string $search Text a tag or its note's title contains; empty for every tag.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a field or the join is declared wrongly.
     *
     * @return list<NoteTag> The tags with their notes, by tag, then by the note's title.
     */
    public function tags(string $search): array
    {
        $rows = $this->entityStore->query($this->noteTypes->noteTags())
            ->with('note_id', 'note', true)
            ->search(['tag', 'note.title'], $search)
            ->orderBy('tag')
            ->orderBy('note.title')
            ->rows();

        return array_map(self::tag(...), $rows);
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a field is declared wrongly.
     *
     * @return array<string, int> How many notes carry each tag, most first (#191).
     */
    public function counts(): array
    {
        return $this->entityStore->query($this->noteTypes->noteTags())->totals()->countBy('tag');
    }

    /**
     * @param int    $noteId A note's key.
     * @param string $tag    A tag.
     *
     * @throws QueryException           When the note does not exist, or a write fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function add(int $noteId, string $tag): void
    {
        $held = $this->entityStore->query($this->noteTypes->noteTags())->where('note_id', $noteId)->where('tag', $tag)->first();

        if ($held instanceof Row) {
            return;
        }

        $this->entityStore->insert($this->noteTypes->noteTags(), ['note_id' => $noteId, 'tag' => $tag]);
    }

    /**
     * @param Row $row A `note_tags` row with its note's columns as `note_*`.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return NoteTag The tag.
     */
    private static function tag(Row $row): NoteTag
    {
        return new NoteTag($row->string('tag'), $row->int('note_id'), $row->stringOrNull('note_title') ?? '');
    }
}
