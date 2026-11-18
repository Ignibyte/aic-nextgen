<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * A computed column (#311): how alike a text field is to a value by
 * trigrams, from 0 to 1, read as text (pg_trgm's `similarity`; the
 * migration names `extension('pg_trgm')`).
 */
final readonly class Similarity
{
    /**
     * @param string $alias The column's name in the row, an identifier.
     * @param string $field A text field of the type.
     * @param string $text  What it is compared with, bound.
     *
     * @throws InvalidArgumentException When the alias is not an identifier.
     */
    public function __construct(
        public string $alias,
        public string $field,
        public string $text,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $alias) !== 1) {
            throw new InvalidArgumentException('A computed column is named by an identifier; got "' . $alias . '".');
        }
    }
}
