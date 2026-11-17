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
use App\Note\Entity\NoteMark;
use InvalidArgumentException;

use function array_map;
use function array_reverse;

/**
 * The `note_marks` rows over the entity store, made by bin/aic make:entity
 * (#123), with the writes GET /note-marks/round-trip makes by key (#307).
 */
final readonly class NoteMarkRepository
{
    /**
     * @param EntityStore $entityStore The entity store.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
    }

    /**
     * @param int $count How many of the newest rows to read.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the count is below 1, or a constructed value is invalid.
     *
     * @return list<NoteMark> The newest rows, at most that many, oldest first.
     */
    public function latest(int $count): array
    {
        $noteMarkRows = $this->entityStore->query($this->noteMarkType())
            ->orderByDescending('created_at')
            ->limit($count)
            ->rows()
            ->all();

        return array_map(self::noteMark(...), array_reverse($noteMarkRows));
    }

    /**
     * @param array<string, string> $values The mark's fields by name.
     *
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws QueryException           When the insert fails.
     *
     * @return string The new mark's key, a uuid.
     */
    public function add(array $values): string
    {
        return $this->entityStore->insertUuid($this->noteMarkType(), $values);
    }

    /**
     * @param string $key A mark's key.
     *
     * @throws InvalidArgumentException When a constructed value is invalid.
     * @throws QueryException           When the query fails.
     *
     * @return NoteMark|null The mark; null when there is none.
     */
    public function find(string $key): ?NoteMark
    {
        $row = $this->entityStore->query($this->noteMarkType())->where('id', $key)->rows()->first();

        return $row instanceof Row
            ? self::noteMark($row)
            : null;
    }

    /**
     * @param string $key  A mark's key.
     * @param string $kind Its new kind.
     *
     * @throws InvalidArgumentException When the key or the kind does not fit.
     * @throws EntityNotFoundException  When there is no such mark.
     * @throws QueryException           When the update fails.
     */
    public function rekind(string $key, string $kind): void
    {
        $this->entityStore->update($this->noteMarkType(), $key, ['kind' => $kind]);
    }

    /**
     * @param string $key A mark's key.
     *
     * @throws InvalidArgumentException When the entity type is invalid.
     * @throws EntityNotFoundException  When there is no such mark.
     * @throws QueryException           When the delete fails.
     */
    public function remove(string $key): void
    {
        $this->entityStore->delete($this->noteMarkType(), $key);
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `note_marks` table.
     */
    private function noteMarkType(): EntityType
    {
        return new EntityType('note_marks', 'id', [
            new Field('label', FieldType::Text),
            new Field('kind', FieldType::Choice, choices: ['pin', 'flag']),
            new Field('meta', FieldType::Json),
            new Field('marked_at', FieldType::Timestamp),
            new Field('origin', FieldType::Uuid),
            new Field('weight', FieldType::Numeric),
            new Field('created_at', FieldType::CreatedAt),
        ], keyType: FieldType::Uuid);
    }

    /**
     * @param Row $row A `note_marks` row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return NoteMark Its entity.
     */
    private static function noteMark(Row $row): NoteMark
    {
        return new NoteMark(
            $row->string('label'),
            $row->string('kind'),
            $row->string('meta'),
            $row->dateTime('marked_at'),
            $row->string('origin'),
            $row->string('weight'),
        );
    }
}
