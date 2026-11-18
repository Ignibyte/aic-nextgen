<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\DeletedScope;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;

use function count;

/**
 * GET /ledger/accounts/round-trip's work (#309): one account opened, renamed,
 * deleted (a stamp), seen only when asked, restored, deleted again and purged,
 * every write credited to `ledger-round-trip`; then its audit trail, which
 * outlives the purge. Opening it queues the listener's job, whose notice
 * GET /ledger/notices reads after the worker.
 */
final readonly class LedgerAccounts
{
    private const string ACTOR = 'ledger-round-trip';

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
        $entityStore->update($accounts, $key, ['name' => 'Round trip, renamed']);
        $entityStore->delete($accounts, $key);

        $lines = ['deleted: ' . $this->seen($key)];
        $restored = $entityStore->query($accounts)->withDeleted(DeletedScope::Deleted)->where('id', $key)->changes()->restore();
        $lines[] = 'restored ' . $restored . ': ' . $this->seen($key);
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
