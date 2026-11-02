<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One row of a `Table` (#230).
 */
final readonly class TableRow
{
    /**
     * @param list<TableCell> $cells Its cells, one per column.
     * @param string          $state A state its class names (`aic-row-<state>`), such as `warn`; empty for none.
     */
    public function __construct(
        public array $cells,
        public string $state = '',
    ) {
    }
}
