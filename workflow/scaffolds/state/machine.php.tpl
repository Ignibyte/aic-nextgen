<?php

declare(strict_types=1);

namespace {namespace}\Entity;

use AIC\Entity\Domain\StateMachine;
use AIC\Entity\Domain\Transition;
use InvalidArgumentException;

/**
 * The `{table}.{field}` state machine, made by bin/aic make:state (#308): its
 * states and the transitions between them, each with the permission it
 * needs (empty for none). `EntityTransitions::apply()` takes them. A made
 * machine has no caller until the entity type takes it; it is API for it.
 *
 * @api
 */
final readonly class {class}
{
    /**
     * @throws InvalidArgumentException When the machine is declared wrongly.
     *
     * @return StateMachine The states, `{initial}` first, and the transitions.
     */
    public function machine(): StateMachine
    {
        $states = [{states}
        ];

        return new StateMachine('{field}', $states, [{calls}
        ]);
    }{methods}
}
