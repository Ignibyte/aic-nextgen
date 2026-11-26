<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\DeletedScope;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;

use function array_key_exists;
use function count;
use function in_array;

/**
 * GET /ledger/accounts/round-trip's work (#309): one account opened, renamed,
 * deleted (a stamp), seen only when asked, restored, deleted again and purged,
 * its name read by the totals with no condition once deleted and once restored (#348),
 * every write credited to `ledger-round-trip`; then its audit trail, which
 * outlives the purge. Opening it queues the listener's job, whose notice
 * GET /ledger/notices reads after the worker.
 */
final readonly class LedgerAccounts
{
    private const string ACTOR = 'ledger-round-trip';

    private const string RENAMED = 'Round trip, renamed';

    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param LedgerHistory $ledgerHistory The account's history as lines.
     * @param LedgerTypes   $ledgerTypes   The ledger's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private LedgerHistory $ledgerHistory,
        private LedgerTypes $ledgerTypes,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a value does not fit, or the type is declared wrongly.
     * @throws EntityNotFoundException  When the account is gone before a step that needs it.
     * @throws QueryException           When a write, its record or a read fails.
     *
     * @return LedgerLines Each step and what came of it.
     */
    public function body(): LedgerLines
    {
        $accounts = $this->ledgerTypes->accounts();
        $entityStore = $this->entityStore->withActor(self::ACTOR);
        $key = $entityStore->insert($accounts, ['name' => 'Round trip']);
        $entityStore->update($accounts, $key, ['name' => self::RENAMED]);
        $entityStore->delete($accounts, $key);

        $lines = ['deleted: ' . $this->seen($key), 'totals: ' . $this->totals()];
        $restored = $entityStore->query($accounts)->withDeleted(DeletedScope::Deleted)->where('id', $key)->changes()->restore();
        $lines[] = 'restored ' . $restored . ': ' . $this->seen($key);
        $lines[] = 'totals: ' . $this->totals();
        $entityStore->delete($accounts, $key);
        $purged = $entityStore->query($accounts)->withDeleted()->where('id', $key)->changes()->purge();
        $lines[] = 'purged ' . $purged . ': ' . $this->seen($key);

        $lines = [...$lines, ...$this->ledgerHistory->lines($accounts, $key)];

        return new LedgerLines('steps', [...$lines, ...$this->refused()]);
    }

    /**
     * @param int $key The account's key.
     *
     * @throws InvalidArgumentException When the query is declared wrongly.
     * @throws QueryException           When a query fails.
     *
     * @return string How many rows a plain query sees, one with `withDeleted()` and one with only the deleted rows.
     */
    private function seen(int $key): string
    {
        $accounts = $this->ledgerTypes->accounts();
        $live = $this->entityStore->query($accounts)->where('id', $key)->rows()->all();
        $all = $this->entityStore->query($accounts)->withDeleted()->where('id', $key)->rows()->all();
        $deleted = $this->entityStore->query($accounts)->withDeleted(DeletedScope::Deleted)->where('id', $key)->rows()->all();

        return 'live ' . count($live) . ', all ' . count($all) . ', deleted ' . count($deleted);
    }

    /**
     * @throws InvalidArgumentException When the type has no name field.
     * @throws QueryException           When a query fails.
     *
     * @return string Whether the renamed account's name is among the live names, by `values()` and by
     *                `countBy()`, each read with no condition, so the WHERE holds the live scope alone (#348).
     */
    private function totals(): string
    {
        $entityTotals = $this->entityStore->query($this->ledgerTypes->accounts())->totals();
        $values = $this->answer(in_array(self::RENAMED, $entityTotals->values('name'), true));
        $countBy = $this->answer(array_key_exists(self::RENAMED, $entityTotals->countBy('name')));

        return 'values ' . $values . ', count-by ' . $countBy;
    }

    /**
     * @param bool $held Whether the name was there.
     *
     * @return string `yes` or `no`.
     */
    private function answer(bool $held): string
    {
        return $held
            ? 'yes'
            : 'no';
    }

    /**
     * @throws QueryException When a write fails for a reason other than its refusal.
     *
     * @return list<string> What the store refuses before any SQL: writing the deleted field, and an upsert.
     */
    private function refused(): array
    {
        $accounts = $this->ledgerTypes->accounts();
        $lines = [];

        try {
            $this->entityStore->insert($accounts, ['deleted_at' => '2026-11-18T00:00:00+00:00', 'name' => 'Refused']);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $lines[] = 'write deleted_at: ' . $invalidArgumentException->getMessage();
        }

        try {
            $this->entityStore->upsert($accounts, ['name' => 'Refused'], 'name');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $lines[] = 'upsert: ' . $invalidArgumentException->getMessage();
        }

        return $lines;
    }
}
