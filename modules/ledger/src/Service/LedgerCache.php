<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Cache\Application\PostgresCache;
use AIC\Cache\Application\PostgresLocks;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * GET /ledger/cache's work (#312): a value put, read and forgotten, one
 * remembered across two reads, a counter counted up and down, a lock held
 * around work, tried while free, refused outside a transaction and held to
 * the end of one. It forgets its keys, so it leaves the cache as it found it.
 */
final readonly class LedgerCache
{
    /**
     * @param PostgresCache $postgresCache The cache.
     * @param PostgresLocks $postgresLocks The locks.
     * @param EntityStore   $entityStore   Opens the transaction a transaction lock needs.
     */
    public function __construct(
        private PostgresCache $postgresCache,
        private PostgresLocks $postgresLocks,
        private EntityStore $entityStore,
    ) {
    }

    /**
     * @throws Throwable When a key is malformed, a read, a write or a lock fails, or a locked closure throws.
     *
     * @return LedgerLines Each step and what came of it.
     */
    public function body(): LedgerLines
    {
        $ledgerTrail = new LedgerTrail();
        $this->values($ledgerTrail);
        $this->locks($ledgerTrail);

        return new LedgerLines('steps', $ledgerTrail->lines());
    }

    /**
     * @param LedgerTrail $ledgerTrail The trail.
     *
     * @throws InvalidArgumentException When a key is malformed.
     * @throws QueryException           When a read or a write fails.
     */
    private function values(LedgerTrail $ledgerTrail): void
    {
        $postgresCache = $this->postgresCache;
        $postgresCache->forget('ledger:greeting');

        $ledgerTrail->note('before', $postgresCache->get('ledger:greeting') ?? 'missing');
        $postgresCache->put('ledger:greeting', 'hello', 60);
        $ledgerTrail->note('put', $postgresCache->get('ledger:greeting') ?? 'missing');
        $postgresCache->forget('ledger:greeting');
        $ledgerTrail->note('forgotten', $postgresCache->get('ledger:greeting') ?? 'missing');
        $postgresCache->forget('ledger:computed');
        $first = $postgresCache->remember('ledger:computed', 60, static fn (): string => 'computed first');
        $second = $postgresCache->remember('ledger:computed', 60, static fn (): string => 'computed again');
        $ledgerTrail->note('remembered', $first . ', then ' . $second);
        $postgresCache->forget('ledger:computed');
        $postgresCache->forget('ledger:hits');

        $counts = [
            $postgresCache->increment('ledger:hits', 1, 60),
            $postgresCache->increment('ledger:hits', 2, 60),
            $postgresCache->increment('ledger:hits', -1, 60),
        ];
        $ledgerTrail->note('counted', $counts[0] . ', ' . $counts[1] . ', ' . $counts[2]);
        $postgresCache->forget('ledger:hits');
    }

    /**
     * @param LedgerTrail $ledgerTrail The trail.
     *
     * @throws Throwable When a key is malformed, a read, a write or a lock fails, or a locked closure throws.
     */
    private function locks(LedgerTrail $ledgerTrail): void
    {
        $this->postgresLocks->around('ledger:close-month', static function () use ($ledgerTrail): void {
            $ledgerTrail->note('around', 'held');
        });
        $free = $this->postgresLocks->tryAround('ledger:close-month', static function () use ($ledgerTrail): void {
            $ledgerTrail->note('tried', 'held');
        });
        $ledgerTrail->note('free', $free ? 'yes' : 'no');

        try {
            $this->postgresLocks->holdForTransaction('ledger:close-month');
        } catch (LogicException $logicException) {
            $ledgerTrail->note('outside a transaction', $logicException->getMessage());
        }

        $postgresLocks = $this->postgresLocks;
        $this->entityStore->transaction(static function () use ($postgresLocks, $ledgerTrail): void {
            $postgresLocks->holdForTransaction('ledger:close-month');
            $ledgerTrail->note('inside a transaction', 'held');
        });
    }
}
