<?php

declare(strict_types=1);

namespace App\Scaffold\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use AIC\Entity\Domain\Relation;
use AIC\Entity\Domain\RelationKind;
use InvalidArgumentException;

/**
 * The scaffold module's entity types, one accessor per table, made by bin/aic make:entity and given their relations by
 * bin/aic make:relation (#328). A relation's target is built without relations of its own, so no type embeds itself.
 */
final readonly class ScaffoldTypes
{
    /**
     * @throws InvalidArgumentException When a field or a relation is invalid.
     *
     * @return EntityType The `scaffold_tasks` table, with its relations.
     */
    public function scaffoldTask(): EntityType
    {
        return $this->scaffoldTaskWith([
            new Relation(
                'scaffold_events',
                RelationKind::ManyToMany,
                target: $this->scaffoldEventWith([]),
                key: 'scaffold_task_id',
                pivot: $this->scaffoldTasksScaffoldEvents(),
                otherKey: 'scaffold_event_id',
            ),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field or a relation is invalid.
     *
     * @return EntityType The `scaffold_events` table, with its relations.
     */
    public function scaffoldEvent(): EntityType
    {
        return $this->scaffoldEventWith([
            new Relation(
                'scaffold_tasks',
                RelationKind::ManyToMany,
                target: $this->scaffoldTaskWith([]),
                key: 'scaffold_event_id',
                pivot: $this->scaffoldTasksScaffoldEvents(),
                otherKey: 'scaffold_task_id',
            ),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `scaffold_tasks_scaffold_events` pivot, made by bin/aic make:relation.
     */
    public function scaffoldTasksScaffoldEvents(): EntityType
    {
        return new EntityType('scaffold_tasks_scaffold_events', 'id', [
            new Field('scaffold_task_id', FieldType::Integer),
            new Field('scaffold_event_id', FieldType::Uuid),
            new Field('note', FieldType::Text),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field or a relation is invalid.
     *
     * @return EntityType The `scaffold_loans` table, with its relations.
     */
    public function scaffoldLoan(): EntityType
    {
        return $this->scaffoldLoanWith([]);
    }

    /**
     * @param list<object> $relations Its Relations.
     *
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `scaffold_tasks` table.
     */
    private function scaffoldTaskWith(array $relations): EntityType
    {
        return new EntityType('scaffold_tasks', 'id', [
            new Field('title', FieldType::Text),
            new Field('due_on', FieldType::Date),
            new Field('done', FieldType::Boolean),
            new Field('created_at', FieldType::CreatedAt),
        ], relations: $relations);
    }

    /**
     * @param list<object> $relations Its Relations.
     *
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `scaffold_events` table.
     */
    private function scaffoldEventWith(array $relations): EntityType
    {
        return new EntityType('scaffold_events', 'id', [
            new Field('label', FieldType::Text),
            new Field('state', FieldType::Choice, choices: ['open', 'shut']),
            new Field('payload', FieldType::Json),
            new Field('happened_at', FieldType::Timestamp),
            new Field('source', FieldType::Uuid),
            new Field('created_at', FieldType::CreatedAt),
        ], keyType: FieldType::Uuid, relations: $relations);
    }

    /**
     * @param list<object> $relations Its Relations.
     *
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `scaffold_loans` table.
     */
    private function scaffoldLoanWith(array $relations): EntityType
    {
        return new EntityType('scaffold_loans', 'id', [
            new Field('book_id', FieldType::Uuid),
            new Field('task_id', FieldType::Reference, nullable: true, references: new EntityType('scaffold_tasks', 'id', [])),
            new Field('borrower', FieldType::Text),
            new Field('lent_at', FieldType::Timestamp),
            new Field('returned_at', FieldType::Timestamp, nullable: true),
        ], relations: $relations);
    }
}
