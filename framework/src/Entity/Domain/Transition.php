<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * One edge of a state machine (#308): its name, the states it leaves, the
 * state it enters, and the permission an actor needs to take it (#304),
 * empty for any actor.
 */
final readonly class Transition
{
    /**
     * @param string       $name       Its name, such as `approve`.
     * @param list<string> $from       The states it may leave, one or more.
     * @param string       $into       The state it enters.
     * @param string       $permission The permission it needs, such as `invoices.approve`; empty for none.
     *
     * @throws InvalidArgumentException When the name is not a word, or it leaves no state.
     */
    public function __construct(
        public string $name,
        public array $from,
        public string $into,
        public string $permission = '',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/', $name) !== 1 || $from === []) {
            throw new InvalidArgumentException('A transition is named in lower case and leaves at least one state; got "' . $name . '".');
        }
    }
}
