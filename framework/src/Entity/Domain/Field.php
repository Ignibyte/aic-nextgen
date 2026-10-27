<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * One field of an entity type: a column, its type, whether it may be null, and for a reference the entity
 * type it points to.
 */
final readonly class Field
{
    /**
     * @param string          $name       The column: lower case letters, digits and underscores.
     * @param FieldType       $type       Its type.
     * @param bool            $nullable   Whether it may hold null (#184).
     * @param EntityType|null $references For a Reference field, the entity type whose key it holds (#190).
     *
     * @throws InvalidArgumentException When the name is not an identifier, or a Reference field names no entity type
     *                                  or another field names one.
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public bool $nullable = false,
        public ?EntityType $references = null,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $name) !== 1) {
            throw new InvalidArgumentException('Field "' . $name . '": a field name is an identifier.');
        }

        if (($type === FieldType::Reference) !== ($references instanceof EntityType)) {
            throw new InvalidArgumentException(
                'Field "' . $name . '": a Reference field, and only one, names the entity type it holds the key of.',
            );
        }
    }
}
