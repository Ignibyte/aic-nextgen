<?php

declare(strict_types=1);

namespace App\Scaffold\Entity;

use AIC\Entity\Domain\StateMachine;
use AIC\Entity\Domain\Transition;
use App\Scaffold\Enum\ScaffoldTaskStage;
use InvalidArgumentException;

/**
 * The `scaffold_tasks.stage` state machine, made by bin/aic make:state (#308): its
 * states and the transitions between them, each with the permission it
 * needs (empty for none). `EntityTransitions::apply()` takes them. A made
 * machine has no caller until the entity type takes it; it is API for it.
 *
 * @api
 */
final readonly class ScaffoldTaskStageTransitions
{
    /**
     * @throws InvalidArgumentException When the machine is declared wrongly.
     *
     * @return StateMachine The states, `open` first, and the transitions.
     */
    public function machine(): StateMachine
    {
        $states = [
            ScaffoldTaskStage::Open->value,
            ScaffoldTaskStage::Done->value,
            ScaffoldTaskStage::Archived->value,
        ];

        return new StateMachine('stage', $states, [
            $this->finish(),
            $this->archive(),
        ]);
    }

    /**
     * @throws InvalidArgumentException When the transition is declared wrongly.
     *
     * @return Transition `finish`: from open to done, for any actor.
     */
    private function finish(): Transition
    {
        return new Transition(
            'finish',
            [ScaffoldTaskStage::Open->value],
            ScaffoldTaskStage::Done->value,
            '',
        );
    }

    /**
     * @throws InvalidArgumentException When the transition is declared wrongly.
     *
     * @return Transition `archive`: from open or done to archived, for an actor holding scaffold.archive.
     */
    private function archive(): Transition
    {
        return new Transition(
            'archive',
            [ScaffoldTaskStage::Open->value, ScaffoldTaskStage::Done->value],
            ScaffoldTaskStage::Archived->value,
            'scaffold.archive',
        );
    }
}
