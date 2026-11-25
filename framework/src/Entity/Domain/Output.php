<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * One column of a derived query's rows (#329): a column the query reads (a
 * field, a joined `prefix_field` or a computed column) under a name, or a
 * fixed text, such as which branch of a union a row came from.
 */
final readonly class Output
{
    /**
     * @param string      $name   The column's name in the rows, an identifier.
     * @param string      $column The column it reads; empty for a fixed text.
     * @param string|null $text   The fixed text, bound; null when it reads a column.
     *
     * @throws InvalidArgumentException When the name is not an identifier, or it has both a column and a text or neither.
     */
    public function __construct(
        public string $name,
        public string $column,
        public ?string $text = null,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $name) !== 1) {
            throw new InvalidArgumentException('An output is named by an identifier; got "' . $name . '".');
        }

        if (($column === '') === ($text === null)) {
            throw new InvalidArgumentException('Output "' . $name . '" reads a column, or is a fixed text.');
        }
    }
}
