<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\FieldFormats;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The store's checks of an entity type's key (#307): which insert a type
 * takes, and whether a value is one of its keys.
 */
final readonly class EntityKeys
{
    /**
     * @param EntityType $entityType An entity type `insert()` or `insertIfAbsent()` writes.
     *
     * @throws InvalidArgumentException When it is keyed by uuid.
     */
    public function integerKeyed(EntityType $entityType): void
    {
        self::keyedBy($entityType, FieldType::Integer, 'insertUuid()');
    }

    /**
     * @param EntityType $entityType An entity type `insertUuid()` writes.
     *
     * @throws InvalidArgumentException When it is keyed by an integer.
     */
    public function uuidKeyed(EntityType $entityType): void
    {
        self::keyedBy($entityType, FieldType::Uuid, 'insert()');
    }

    /**
     * @param EntityType $entityType An entity type.
     * @param int|string $key        A value.
     *
     * @return bool Whether it is one of the type's keys: an int, or a uuid for a uuid-keyed type.
     */
    public function isKey(EntityType $entityType, int|string $key): bool
    {
        return (new FieldFormats())->key($entityType->keyType, $key);
    }

    /**
     * @param EntityType $entityType An entity type.
     * @param FieldType  $fieldType  The key type the method writes.
     * @param string     $instead    The method for the other key type.
     *
     * @throws InvalidArgumentException When the entity type is keyed otherwise.
     */
    private static function keyedBy(EntityType $entityType, FieldType $fieldType, string $instead): void
    {
        if ($entityType->keyType !== $fieldType) {
            throw new InvalidArgumentException('The ' . $entityType->table . ' entity is keyed otherwise: write it with ' . $instead . '.');
        }
    }
}
