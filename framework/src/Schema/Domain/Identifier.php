<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * A table or column name: lower case letters, digits and underscores, starting
 * with a letter or an underscore, up to 63 characters (Postgres's limit). The
 * DDL writes it as it is, with no quoting.
 */
final readonly class Identifier
{
    /**
     * @param string $value The name.
     *
     * @throws InvalidArgumentException When it is not an identifier.
     */
    public function __construct(
        public string $value,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $value) !== 1) {
            throw new InvalidArgumentException('"' . $value . '" is not an identifier: lower case, digits and underscores, up to 63.');
        }
    }
}
