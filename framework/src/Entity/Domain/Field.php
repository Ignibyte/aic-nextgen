<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * One field of an entity type: a column, its type and whether it may be null.
 */
final readonly class Field
{
    /**
     * @param string    $name     The column: lower case letters, digits and underscores.
     * @param FieldType $type     Its type.
     * @param bool      $nullable Whether it may hold null (#184).
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public bool $nullable = false,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $name) !== 1) {
            throw new InvalidArgumentException('Field "' . $name . '": a field name is an identifier.');
        }
    }
}
