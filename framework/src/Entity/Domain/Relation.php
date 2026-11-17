<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function in_array;
use function preg_match;

/**
 * One relation an entity type declares (#306), by name. A HasMany or HasOne
 * names the target type and the target's column that holds the owner's key;
 * a ManyToMany also names the pivot type, whose `key` column holds the owner's
 * key and `otherKey` column the target's; a Polymorphic names the owner's id
 * field (`key`), its type field, and each type name with its entity type. A
 * target is built without relations back to its owner, as a Reference's is.
 */
final readonly class Relation
{
    /**
     * @param string                    $name      The relation's name: lower case letters, digits and underscores.
     * @param RelationKind              $kind      Its kind.
     * @param EntityType|null           $target    The related type; null for a Polymorphic.
     * @param string                    $key       The column that holds the owner's key: the target's (HasMany, HasOne),
     *                                             the pivot's (ManyToMany); for a Polymorphic, the owner's id field.
     * @param EntityType|null           $pivot     A ManyToMany's pivot type; null for every other kind.
     * @param string                    $otherKey  A ManyToMany's pivot column that holds the target's key.
     * @param string                    $typeField A Polymorphic's owner field that names the type.
     * @param array<string, EntityType> $types     A Polymorphic's types, by the name its type field holds.
     *
     * @throws InvalidArgumentException When the name is not an identifier or the parts do not fit the kind.
     */
    public function __construct(
        public string $name,
        public RelationKind $kind,
        public ?EntityType $target = null,
        public string $key = '',
        public ?EntityType $pivot = null,
        public string $otherKey = '',
        public string $typeField = '',
        public array $types = [],
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,40}$/', $name) !== 1 || !$this->fits()) {
            throw new InvalidArgumentException('Relation "' . $name . '": its name is an identifier, and its parts fit its kind.');
        }
    }

    /**
     * @return bool Whether the parts the kind needs are there, each column on its type, and no other.
     */
    private function fits(): bool
    {
        return match ($this->kind) {
            RelationKind::HasMany, RelationKind::HasOne => $this->owned(),
            RelationKind::ManyToMany => $this->through(),
            RelationKind::Polymorphic => $this->polymorphic(),
        };
    }

    /**
     * @return bool Whether a HasMany or HasOne names a target that holds its key column, and no pivot or types.
     */
    private function owned(): bool
    {
        return $this->holds($this->target, $this->key) && !$this->pivot instanceof EntityType && $this->types === [];
    }

    /**
     * @return bool Whether a ManyToMany names a target and a pivot that holds both key columns, and no types.
     */
    private function through(): bool
    {
        return $this->target instanceof EntityType && $this->holds($this->pivot, $this->key)
            && $this->holds($this->pivot, $this->otherKey) && $this->types === [];
    }

    /**
     * @return bool Whether a Polymorphic names its id and type fields and its types, and no target or pivot.
     */
    private function polymorphic(): bool
    {
        return !$this->target instanceof EntityType && !$this->pivot instanceof EntityType
            && $this->key !== '' && $this->typeField !== '' && $this->types !== [];
    }

    /**
     * @param EntityType|null $entityType A type.
     * @param string          $column     One of its columns.
     *
     * @return bool Whether the type is there and has the column.
     */
    private function holds(?EntityType $entityType, string $column): bool
    {
        return $entityType instanceof EntityType && in_array($column, $entityType->columns(), true);
    }
}
