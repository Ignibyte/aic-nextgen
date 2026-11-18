<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * A computed column (#311): the text at a path in a Json field, such as a
 * profile's `address.city`; null where the path is missing.
 */
final readonly class JsonValue
{
    /**
     * @param string       $alias The column's name in the row, an identifier.
     * @param string       $field A Json field of the type.
     * @param list<string> $path  The keys (or array indexes) to follow, one or more, each letters, digits and `_`.
     *
     * @throws InvalidArgumentException When the alias is not an identifier, or the path is empty or malformed.
     */
    public function __construct(
        public string $alias,
        public string $field,
        public array $path,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $alias) !== 1 || $path === []) {
            throw new InvalidArgumentException('A JSON value is named by an identifier and follows a path of one or more keys.');
        }

        foreach ($path as $key) {
            if (preg_match('/^[A-Za-z0-9_]{1,63}$/', $key) !== 1) {
                throw new InvalidArgumentException('A JSON path key is letters, digits and _; got "' . $key . '".');
            }
        }
    }
}
