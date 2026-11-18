<?php

declare(strict_types=1);

namespace App\Ledger\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;

use function array_merge;

/**
 * Ledger entries and their reviews (#310), written and read through the
 * entity store. The database's guards decide which writes hold.
 */
final readonly class LedgerRepository
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param LedgerTypes $ledgerTypes The ledger's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private LedgerTypes $ledgerTypes,
    ) {
    }

    /**
     * @param array<string, string|int|bool|null> $values Values over a three-crate entry of opening stock at 2.50.
     *
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws QueryException           When the database refuses the row.
     *
     * @return int The new entry's key.
     */
    public function entry(array $values): int
    {
        $entry = ['account' => 'Cash', 'memo' => 'opening stock', 'quantity' => 3, 'unit_price' => '2.50'];

        return $this->entityStore->insert($this->ledgerTypes->entries(), array_merge($entry, $values));
    }

    /**
     * @param int $key An entry's key.
     *
     * @throws InvalidArgumentException When the query is declared wrongly.
     * @throws EntityNotFoundException  When there is no such entry.
     * @throws QueryException           When the query fails.
     *
     * @return Row The entry, its generated total and label included.
     */
    public function read(int $key): Row
    {
        return $this->entityStore->query($this->ledgerTypes->entries())->where('id', $key)->one();
    }

    /**
     * @param int                                 $key    An entry's key.
     * @param array<string, string|int|bool|null> $values What to change.
     *
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws EntityNotFoundException  When there is no such entry.
     * @throws QueryException           When the database refuses the change.
     */
    public function change(int $key, array $values): void
    {
        $this->entityStore->update($this->ledgerTypes->entries(), $key, $values);
    }

    /**
     * @param int $key An entry's key.
     *
     * @throws EntityNotFoundException When there is no such entry.
     * @throws QueryException          When the database refuses the delete.
     */
    public function remove(int $key): void
    {
        $this->entityStore->delete($this->ledgerTypes->entries(), $key);
    }

    /**
     * @param int                                 $entry  The entry's key.
     * @param array<string, string|int|bool|null> $values The decision and reviewer, if any; it is not escalated.
     *
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws QueryException           When the database refuses the row.
     *
     * @return int The new review's key.
     */
    public function review(int $entry, array $values): int
    {
        $review = ['entry_id' => $entry, 'escalated' => false];

        return $this->entityStore->insert($this->ledgerTypes->reviews(), array_merge($review, $values));
    }

    /**
     * @param int                                 $key    A review's key.
     * @param array<string, string|int|bool|null> $values What to change.
     *
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws EntityNotFoundException  When there is no such review.
     * @throws QueryException           When the database refuses the change.
     */
    public function decide(int $key, array $values): void
    {
        $this->entityStore->update($this->ledgerTypes->reviews(), $key, $values);
    }

    /**
     * @param int $key A review's key.
     *
     * @throws EntityNotFoundException When there is no such review.
     * @throws QueryException          When the database refuses the delete.
     */
    public function withdraw(int $key): void
    {
        $this->entityStore->delete($this->ledgerTypes->reviews(), $key);
    }
}
