<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Access\Domain\Actor;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Domain\EntityEvent;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\StateMachine;
use AIC\Entity\Domain\Transition;
use InvalidArgumentException;

use function array_fill;
use function array_merge;
use function count;
use function implode;

/**
 * Moves a row along its type's state machine (#308). A transition is one
 * UPDATE whose WHERE holds the edge, so the check and the write cannot race;
 * it runs through the EntityLifecycle (#309), so an audited type keeps a
 * `transitioned` row credited to the actor and its listeners hear it. A
 * refused or illegal edge throws; it never writes.
 */
final readonly class EntityTransitions
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param EntityType $entityType A type with a state machine.
     * @param int|string $key        One of its rows' keys.
     * @param string     $name       One of its machine's transitions.
     * @param Actor      $actor      Who takes it (#304): its permission is asked of them, and the audit credits them.
     *
     * @throws InvalidArgumentException   When the type has no state machine, or the machine no such transition.
     * @throws TransitionRefusedException When the actor lacks the transition's permission, or the row is in a state
     *                                    the transition does not leave.
     * @throws EntityNotFoundException    When no live row has the key.
     * @throws QueryException             When the write, its record or the read fails.
     */
    public function apply(EntityType $entityType, int|string $key, string $name, Actor $actor): void
    {
        $stateMachine = $entityType->stateMachine;

        if (!$stateMachine instanceof StateMachine) {
            throw new InvalidArgumentException('The ' . $entityType->table . ' entity has no state machine.');
        }

        $transition = $this->permitted($stateMachine->transition($name), $actor);

        $entityLifecycle = new EntityLifecycle($this->database, $actor->userId);
        $rows = $entityLifecycle->write(
            $entityType,
            EntityEvent::Transitioned,
            $this->sql($entityType, $stateMachine, $transition),
            array_merge([$transition->into, $key], $transition->from),
            [$stateMachine->field],
            [$transition->into],
        );

        if ($rows->all() !== []) {
            return;
        }

        $this->refused($entityType, $stateMachine, $key, $name);
    }

    /**
     * @param Transition $transition The transition asked for.
     * @param Actor      $actor      Who asks.
     *
     * @throws TransitionRefusedException When the actor lacks its permission.
     *
     * @return Transition It, when the actor may take it.
     */
    private function permitted(Transition $transition, Actor $actor): Transition
    {
        if ($transition->permission !== '' && !$actor->can($transition->permission)) {
            throw new TransitionRefusedException(
                'The ' . $transition->name . ' transition needs the permission ' . $transition->permission . '.',
            );
        }

        return $transition;
    }

    /**
     * @param EntityType   $entityType   The type.
     * @param StateMachine $stateMachine Its machine.
     * @param Transition   $transition   The transition taken.
     *
     * @return string The UPDATE that enters the transition's state from one it leaves, on a live row, returning its key.
     */
    private function sql(EntityType $entityType, StateMachine $stateMachine, Transition $transition): string
    {
        $table = $entityType->table;
        $assignments = [$stateMachine->field . ' = ?'];

        foreach ($entityType->updatedAt() as $column) {
            $assignments[] = $column . ' = now()';
        }

        $live = $entityType->live($table);

        return 'UPDATE ' . $table . ' SET ' . implode(', ', $assignments) . ' WHERE ' . $table . '.' . $entityType->key . ' = ?'
            . ' AND ' . $table . '.' . $stateMachine->field . ' IN (' . implode(', ', array_fill(0, count($transition->from), '?')) . ')'
            . ($live === '' ? '' : ' AND ' . $live)
            . ' RETURNING ' . $table . '.' . $entityType->key . '::text AS aic_key';
    }

    /**
     * @param EntityType   $entityType   The type.
     * @param StateMachine $stateMachine Its machine.
     * @param int|string   $key          The row's key.
     * @param string       $name         The transition refused.
     *
     * @throws InvalidArgumentException   When the query is declared wrongly.
     * @throws EntityNotFoundException    When no live row has the key.
     * @throws TransitionRefusedException Otherwise: the row's state is one the transition does not leave.
     * @throws QueryException             When the read fails.
     */
    private function refused(EntityType $entityType, StateMachine $stateMachine, int|string $key, string $name): never
    {
        $row = (new EntityQuery($this->database, $entityType))->where($entityType->key, $key)->first();

        if (!$row instanceof Row) {
            throw new EntityNotFoundException('No ' . $entityType->table . ' row has that key.');
        }

        throw new TransitionRefusedException('The ' . $name . ' transition does not leave ' . $row->string($stateMachine->field) . '.');
    }
}
