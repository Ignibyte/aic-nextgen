<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * An entity type: a table, its key (a bigint the database generates, or since
 * #307 a uuid), its fields, since #306 its relations and since #309 its
 * lifecycle and since #308 its state machine. Names are identifiers, so the SQL built from
 * them needs no quoting.
 */
final readonly class EntityType
{
    /**
     * @param string            $table        The table, or a view.
     * @param string            $key          The key column.
     * @param list<object>      $fields       Its Fields: objects, the list form elephc keeps (plan.md).
     * @param bool              $readOnly     Whether it maps a view (#191): queried like a table, never written.
     * @param FieldType         $keyType      The key's type: `Integer`, or `Uuid` for a uuid the database generates (#307).
     * @param list<object>      $relations    Its Relations (#306), each named once.
     * @param Lifecycle         $lifecycle    What its writes record, and whether it deletes softly (#309).
     * @param StateMachine|null $stateMachine Its state field's states and transitions (#308); null for none.
     *
     * @throws InvalidArgumentException When the table or the key is not an identifier, the key type is neither, or
     *                                  the soft-delete field is not a nullable Timestamp of the type, or the state
     *                                  field is not a required Choice of the machine's states.
     */
    public function __construct(
        public string $table,
        public string $key,
        private array $fields,
        public bool $readOnly = false,
        public FieldType $keyType = FieldType::Integer,
        private array $relations = [],
        public Lifecycle $lifecycle = new Lifecycle(),
        public ?StateMachine $stateMachine = null,
    ) {
        if ($keyType !== FieldType::Integer && $keyType !== FieldType::Uuid) {
            throw new InvalidArgumentException('Entity type "' . $table . '": a key is an Integer or a Uuid.');
        }

        if (
            preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $table) !== 1
            || preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $key) !== 1
        ) {
            throw new InvalidArgumentException('Entity type "' . $table . '": the table and the key are identifiers.');
        }

        $this->checkDeletedAt();
        $this->checkStateMachine();
    }

    /**
     * @param string $alias The table's name or alias in the query.
     *
     * @return string `<alias>.<deleted field> IS NULL` for a soft-deleting type (#309), so a query or a relation reads
     *                live rows only; empty for any other type.
     */
    public function live(string $alias): string
    {
        return $this->lifecycle->isSoft()
            ? $alias . '.' . $this->lifecycle->deletedAt . ' IS NULL'
            : '';
    }

    /**
     * @param string $name A relation's name.
     *
     * @throws InvalidArgumentException When the type declares no relation so named.
     *
     * @return Relation It (#306).
     */
    public function relation(string $name): Relation
    {
        foreach ($this->relations as $relation) {
            if ($relation instanceof Relation && $relation->name === $name) {
                return $relation;
            }
        }

        throw new InvalidArgumentException('The ' . $this->table . ' entity has no relation "' . $name . '".');
    }

    /**
     * @param string $column One of its columns.
     *
     * @return string How a query reads it: `::text` for a Json field (#307), so every driver hands back Postgres's own
     *                text of the document; empty for any other.
     */
    public function read(string $column): string
    {
        foreach ($this->fields as $field) {
            if ($field instanceof Field && $field->name === $column && $field->type === FieldType::Json) {
                return '::text';
            }
        }

        return '';
    }

    /**
     * @return list<string> The key, then every field's column, in order.
     */
    public function columns(): array
    {
        $columns = [$this->key];

        foreach ($this->fields as $field) {
            if (!($field instanceof Field)) {
                continue;
            }

            $columns[] = $field->name;
        }

        return $columns;
    }

    /**
     * @return list<string> The fields a caller writes, in order: all but the stamps, a soft delete's field (#309),
     *                      which only `delete()` and `restore()` write, and a state field (#308), which only a
     *                      transition writes.
     */
    public function writable(): array
    {
        $names = [];

        foreach ($this->fields as $field) {
            if (
                !($field instanceof Field)
                || !$field->type->isWritable()
                || $field->name === $this->lifecycle->deletedAt
                || $field->name === $this->stateField()
            ) {
                continue;
            }

            $names[] = $field->name;
        }

        return $names;
    }

    /**
     * @return list<string> The CreatedAt fields, which the insert sets to `now()`.
     */
    public function createdAt(): array
    {
        return $this->named(FieldType::CreatedAt);
    }

    /**
     * @return list<string> The UpdatedAt fields, which the insert and every update set to `now()` (#184).
     */
    public function updatedAt(): array
    {
        return $this->named(FieldType::UpdatedAt);
    }

    /**
     * @param string $name A field's column.
     *
     * @throws InvalidArgumentException When the entity type has no such field.
     *
     * @return Field The field.
     */
    public function field(string $name): Field
    {
        foreach ($this->fields as $field) {
            if ($field instanceof Field && $field->name === $name) {
                return $field;
            }
        }

        throw new InvalidArgumentException('The ' . $this->table . ' entity has no field "' . $name . '".');
    }

    /**
     * @return array<string, string> The state field and the state every new row starts in (#308); empty for a type
     *                               with no state machine.
     */
    public function initialState(): array
    {
        return $this->stateMachine instanceof StateMachine
            ? [$this->stateMachine->field => $this->stateMachine->initial()]
            : [];
    }

    /**
     * @return string The state field (#308); empty for a type with no state machine.
     */
    public function stateField(): string
    {
        return $this->stateMachine instanceof StateMachine
            ? $this->stateMachine->field
            : '';
    }

    /**
     * @throws InvalidArgumentException When the state field is not a required Choice whose values are the states.
     */
    private function checkStateMachine(): void
    {
        $stateMachine = $this->stateMachine;

        if (!$stateMachine instanceof StateMachine) {
            return;
        }

        if (!$stateMachine->fits($this->field($stateMachine->field))) {
            throw new InvalidArgumentException(
                'Entity type "' . $this->table . '": a state field is a required Choice whose values are the states, in order.',
            );
        }
    }

    /**
     * @throws InvalidArgumentException When a soft delete's field is not a nullable Timestamp of the type.
     */
    private function checkDeletedAt(): void
    {
        $deletedAt = $this->lifecycle->deletedAt;

        if ($deletedAt === '') {
            return;
        }

        foreach ($this->fields as $field) {
            if (
                $field instanceof Field
                && $field->name === $deletedAt
                && $field->type === FieldType::Timestamp
                && $field->nullable
            ) {
                return;
            }
        }

        throw new InvalidArgumentException(
            'Entity type "' . $this->table . '": a soft delete names one of its nullable Timestamp fields.',
        );
    }

    /**
     * @param FieldType $fieldType A field type.
     *
     * @return list<string> The columns of the fields of that type, in order.
     */
    private function named(FieldType $fieldType): array
    {
        $names = [];

        foreach ($this->fields as $field) {
            if (!$field instanceof Field) {
                continue;
            }

            if ($field->type !== $fieldType) {
                continue;
            }

            $names[] = $field->name;
        }

        return $names;
    }
}
