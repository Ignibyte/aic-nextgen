<?php

declare(strict_types=1);

namespace App\Ledger\Entity;

use AIC\Entity\Domain\StateMachine;
use AIC\Entity\Domain\Transition;
use App\Ledger\Enum\LedgerAccountStanding;
use InvalidArgumentException;

/**
 * The `ledger_accounts.standing` state machine, made by bin/aic make:state (#308): its
 * states and the transitions between them, each with the permission it
 * needs (empty for none). `EntityTransitions::apply()` takes them. The
 * ledger's account type takes it.
 */
final readonly class LedgerAccountStandingTransitions
{
    /**
     * @throws InvalidArgumentException When the machine is declared wrongly.
     *
     * @return StateMachine The states, `open` first, and the transitions.
     */
    public function machine(): StateMachine
    {
        $states = [
            LedgerAccountStanding::Open->value,
            LedgerAccountStanding::Frozen->value,
            LedgerAccountStanding::Closed->value,
        ];

        return new StateMachine('standing', $states, [
            $this->freeze(),
            $this->thaw(),
            $this->close(),
        ]);
    }

    /**
     * @throws InvalidArgumentException When the transition is declared wrongly.
     *
     * @return Transition `freeze`: from open to frozen, for an actor holding ledger.freeze.
     */
    private function freeze(): Transition
    {
        return new Transition(
            'freeze',
            [LedgerAccountStanding::Open->value],
            LedgerAccountStanding::Frozen->value,
            'ledger.freeze',
        );
    }

    /**
     * @throws InvalidArgumentException When the transition is declared wrongly.
     *
     * @return Transition `thaw`: from frozen to open, for any actor.
     */
    private function thaw(): Transition
    {
        return new Transition(
            'thaw',
            [LedgerAccountStanding::Frozen->value],
            LedgerAccountStanding::Open->value,
            '',
        );
    }

    /**
     * @throws InvalidArgumentException When the transition is declared wrongly.
     *
     * @return Transition `close`: from open or frozen to closed, for any actor.
     */
    private function close(): Transition
    {
        return new Transition(
            'close',
            [LedgerAccountStanding::Open->value, LedgerAccountStanding::Frozen->value],
            LedgerAccountStanding::Closed->value,
            '',
        );
    }
}
