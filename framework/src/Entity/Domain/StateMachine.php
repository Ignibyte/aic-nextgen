<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function array_diff;
use function array_unique;
use function count;

/**
 * The states a field may hold and the transitions between them (#308). The
 * first state is where every new row starts; a row leaves it only by a
 * transition, through `EntityTransitions::apply()`.
 */
final readonly class StateMachine
{
    /**
     * @param string       $field       The state field: a required Choice field of the type, whose values are the states.
     * @param list<string> $states      The states, the initial one first, none twice.
     * @param list<object> $transitions Its Transitions: objects, the list form elephc keeps (plan.md).
     *
     * @throws InvalidArgumentException When there are no states, one is repeated, or a transition is named twice or
     *                                  names a state the machine lacks.
     */
    public function __construct(
        public string $field,
        public array $states,
        private array $transitions,
    ) {
        if ($states === [] || count(array_unique($states)) !== count($states)) {
            throw new InvalidArgumentException('State machine "' . $field . '": one or more states, none twice.');
        }

        $this->checkTransitions();
    }

    /**
     * @return string The state a new row starts in.
     */
    public function initial(): string
    {
        return $this->states[0] ?? '';
    }

    /**
     * @param string $name A transition's name.
     *
     * @throws InvalidArgumentException When the machine has no such transition.
     *
     * @return Transition It.
     */
    public function transition(string $name): Transition
    {
        foreach ($this->transitions as $transition) {
            if ($transition instanceof Transition && $transition->name === $name) {
                return $transition;
            }
        }

        throw new InvalidArgumentException('State machine "' . $this->field . '" has no transition "' . $name . '".');
    }

    /**
     * @param Field $field The type's field the machine names.
     *
     * @return bool Whether it can hold the states: a required Choice whose values are the states, in order.
     */
    public function fits(Field $field): bool
    {
        return $field->type === FieldType::Choice && !$field->nullable && $field->choices === $this->states;
    }

    /**
     * @param Transition $transition One of its transitions.
     *
     * @return bool Whether it leaves and enters states of the machine only.
     */
    private function moves(Transition $transition): bool
    {
        return array_diff([...$transition->from, $transition->into], $this->states) === [];
    }

    /**
     * @throws InvalidArgumentException When a transition is named twice or names a state the machine lacks.
     */
    private function checkTransitions(): void
    {
        foreach ($this->transitions as $transition) {
            if (!$transition instanceof Transition) {
                continue;
            }

            if (!$this->moves($transition)) {
                throw new InvalidArgumentException('State machine "' . $this->field . '": transition "' . $transition->name
                    . '" moves between its states only.');
            }
        }

        $this->checkNames();
    }

    /**
     * @throws InvalidArgumentException When a transition is named twice.
     */
    private function checkNames(): void
    {
        $names = [];

        foreach ($this->transitions as $transition) {
            if (!$transition instanceof Transition) {
                continue;
            }

            $names[] = $transition->name;
        }

        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidArgumentException('State machine "' . $this->field . '": each transition is named once.');
        }
    }
}
