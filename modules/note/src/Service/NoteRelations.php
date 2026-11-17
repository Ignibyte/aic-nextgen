<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Condition;
use App\Note\Entity\NoteTypes;
use InvalidArgumentException;

/**
 * GET /notes/relations' work (#306): two notes, a tag on the first, a stock
 * item linked to it with an amount, and mentions of the second note and the
 * stock item; then a count, a sum and both filters over the notes, and each
 * relation loaded once. It deletes what it wrote, so it leaves the tables as
 * it found them.
 */
final readonly class NoteRelations
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param NoteTypes   $noteTypes   The note module's entity types, with their relations.
     */
    public function __construct(
        private EntityStore $entityStore,
        private NoteTypes $noteTypes,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a value or a relation is declared wrongly.
     * @throws EntityNotFoundException  When a row it wrote is gone before it deletes it.
     * @throws QueryException           When a query fails.
     *
     * @return NoteRelationsBody What the queries and loads saw.
     */
    public function body(): NoteRelationsBody
    {
        $notes = $this->noteTypes->notes();
        $first = $this->entityStore->insert($notes, ['body' => 'A note with relations.', 'title' => 'Related first']);
        $second = $this->entityStore->insert($notes, ['body' => 'A note others mention.', 'title' => 'Related second']);
        $stock = $this->entityStore->insert($this->noteTypes->stockItems(), [
            'added_on' => '2026-11-17',
            'certified' => true,
            'name' => 'Related stock',
            'price' => '19.99',
        ]);
        $this->link($first, $second, $stock);

        try {
            return $this->seen($first, $second);
        } finally {
            $this->entityStore->delete($notes, $first);
            $this->entityStore->delete($notes, $second);
            $this->entityStore->delete($this->noteTypes->stockItems(), $stock);
        }
    }

    /**
     * @param int $first  The first note.
     * @param int $second The second note.
     * @param int $stock  The stock item.
     *
     * @throws InvalidArgumentException When a value does not fit.
     * @throws QueryException           When an insert fails.
     */
    private function link(int $first, int $second, int $stock): void
    {
        $this->entityStore->insert($this->noteTypes->noteTags(), ['note_id' => $first, 'tag' => 'red']);
        $entityType = $this->noteTypes->noteMentions();
        $this->entityStore->insert($entityType, ['note_id' => $first, 'subject_id' => $second, 'subject_type' => 'note']);
        $this->entityStore->insert($entityType, ['note_id' => $first, 'subject_id' => $stock, 'subject_type' => 'stock_item']);

        $link = ['amount' => '2.50', 'note_id' => $first, 'stock_item_id' => $stock];
        $this->entityStore->insert($this->noteTypes->notesStockItems(), $link);
    }

    /**
     * @param int $first  The first note.
     * @param int $second The second note.
     *
     * @throws InvalidArgumentException When a relation is declared wrongly.
     * @throws QueryException           When a query fails.
     *
     * @return NoteRelationsBody What each query and load saw, by note title.
     */
    private function seen(int $first, int $second): NoteRelationsBody
    {
        $notes = $this->noteTypes->notes();
        $rows = $this->pair($first, $second)->withCount('tags')->withSum('stock_items', 'price')->orderBy('id')->rows();
        $tagged = $this->pair($first, $second)->withExisting('tags', [new Condition('tag', 'red')])->rows();
        $untagged = $this->pair($first, $second)->withMissing('tags')->rows();
        $entityType = $this->noteTypes->noteMentions();
        $mentions = $this->entityStore->query($entityType)->where('note_id', $first)->orderBy('id')->rows();

        return new NoteRelationsBody(
            $rows,
            [$tagged, $untagged],
            [
                $this->entityStore->related($notes, 'tags', $rows),
                $this->entityStore->related($notes, 'stock_items', $rows),
                $this->entityStore->related($entityType, 'subject', $mentions),
            ],
            $mentions,
        );
    }

    /**
     * @param int $first  The first note.
     * @param int $second The second note.
     *
     * @throws InvalidArgumentException When a condition is declared wrongly.
     *
     * @return EntityQuery A query over the two notes alone.
     */
    private function pair(int $first, int $second): EntityQuery
    {
        return $this->entityStore->query($this->noteTypes->notes())->whereAny([new Condition('id', $first), new Condition('id', $second)]);
    }
}
