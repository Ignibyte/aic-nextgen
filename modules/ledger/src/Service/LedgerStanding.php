<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Access\Domain\Actor;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Application\EntityTransitions;
use AIC\Entity\Application\TransitionRefusedException;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;

/**
 * GET /ledger/accounts/standing's work (#308): one account walked along its
 * standing, a clerk refused the freeze a manager may take, then thawed and
 * closed, a thaw from closed refused as an illegal edge, a direct write and a
 * missing row refused; then its audit trail and its purge, so it leaves no
 * account.
 */
final readonly class LedgerStanding
{
    /**
     * @param EntityStore       $entityStore       The entity store.
     * @param EntityTransitions $entityTransitions Takes each transition.
     * @param LedgerHistory     $ledgerHistory     The account's history as lines.
     * @param LedgerTypes       $ledgerTypes       The ledger's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private EntityTransitions $entityTransitions,
        private LedgerHistory $ledgerHistory,
        private LedgerTypes $ledgerTypes,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a value or the machine is declared wrongly.
     * @throws EntityNotFoundException  When the account is gone before its purge.
     * @throws QueryException           When a write, its record or a read fails.
     *
     * @return LedgerLines Each step and what came of it.
     */
    public function body(): LedgerLines
    {
        $accounts = $this->ledgerTypes->accounts();
        $clerk = new Actor('ledger-clerk', []);
        $manager = new Actor('ledger-manager', ['ledger.freeze']);
        $key = $this->entityStore->withActor($clerk->userId)->insert($accounts, ['name' => 'Standing']);
        $lines = [
            'opened: ' . $this->standing($key),
            'freeze by the clerk: ' . $this->take($key, 'freeze', $clerk),
            'freeze by the manager: ' . $this->take($key, 'freeze', $manager),
            'thaw by the clerk: ' . $this->take($key, 'thaw', $clerk),
            'close by the clerk: ' . $this->take($key, 'close', $clerk),
            'thaw a closed account: ' . $this->take($key, 'thaw', $clerk),
            'thaw a missing account: ' . $this->take(0, 'thaw', $clerk),
        ];
        $lines[] = 'write the standing: ' . $this->written($key);

        $lines = [...$lines, ...$this->ledgerHistory->lines($accounts, $key)];

        $this->entityStore->query($accounts)->withDeleted()->where('id', $key)->changes()->purge();

        return new LedgerLines('steps', $lines);
    }

    /**
     * @param int    $key   The account's key.
     * @param string $name  The transition.
     * @param Actor  $actor Who takes it.
     *
     * @throws InvalidArgumentException When the machine has no such transition.
     * @throws EntityNotFoundException  When the account is gone after the transition.
     * @throws QueryException           When the write, its record or the read fails.
     *
     * @return string The standing it reached, or why it was refused.
     */
    private function take(int $key, string $name, Actor $actor): string
    {
        try {
            $this->entityTransitions->apply($this->ledgerTypes->accounts(), $key, $name, $actor);
        } catch (TransitionRefusedException $transitionRefusedException) {
            return 'refused, ' . $transitionRefusedException->getMessage();
        } catch (EntityNotFoundException $entityNotFoundException) {
            return 'refused, ' . $entityNotFoundException->getMessage();
        }

        return $this->standing($key);
    }

    /**
     * @param int $key The account's key.
     *
     * @throws QueryException When the update fails for a reason other than its refusal.
     *
     * @return string Why the store refused a direct write of the standing.
     */
    private function written(int $key): string
    {
        try {
            $this->entityStore->update($this->ledgerTypes->accounts(), $key, ['standing' => 'closed']);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return $invalidArgumentException->getMessage();
        } catch (EntityNotFoundException $entityNotFoundException) {
            return $entityNotFoundException->getMessage();
        }

        return 'allowed';
    }

    /**
     * @param int $key The account's key.
     *
     * @throws InvalidArgumentException When the query is declared wrongly.
     * @throws EntityNotFoundException  When the account is gone.
     * @throws QueryException           When the query fails.
     *
     * @return string Its standing.
     */
    private function standing(int $key): string
    {
        return $this->entityStore->query($this->ledgerTypes->accounts())->where('id', $key)->one()->string('standing');
    }
}
