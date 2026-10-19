<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * One field of an entity type: a column and its type.
 */
final readonly class Field
{
    /**
     * @param string    $name The column: lower case letters, digits and underscores.
     * @param FieldType $type Its type.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     */
    public function __construct(
        public string $name,
        public FieldType $type,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $name) !== 1) {
            throw new InvalidArgumentException('Field "' . $name . '": a field name is an identifier.');
        }
    }
}
