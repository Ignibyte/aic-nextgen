<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * A computed column (#311): each row's number within its partition, such as
 * an entry's rank among its account's by amount, read as an integer.
 */
final readonly class Window
{
    /**
     * @param string         $alias          The column's name in the row, an identifier.
     * @param WindowFunction $windowFunction How the rows are numbered.
     * @param list<string>   $partitionBy    The fields whose values make a partition; none for one over every row.
     * @param string         $orderBy        The field the rows are ordered by within a partition.
     * @param Direction      $direction      Which way.
     *
     * @throws InvalidArgumentException When the alias is not an identifier.
     */
    public function __construct(
        public string $alias,
        public WindowFunction $windowFunction,
        public array $partitionBy,
        public string $orderBy,
        public Direction $direction,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $alias) !== 1) {
            throw new InvalidArgumentException('A computed column is named by an identifier; got "' . $alias . '".');
        }
    }
}
