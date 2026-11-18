<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * A condition a declaration holds rows to (#310): a check, or the rows a
 * partial index covers. Only `Schema::expressions()` makes one, already
 * rendered by its dialect, so a module never writes its SQL (gate:8,
 * `aic.schemaExpression`).
 */
final readonly class Predicate
{
    /**
     * @param string       $sql     The fragment its dialect rendered.
     * @param list<string> $columns The columns it reads, in order of first use.
     */
    public function __construct(
        public string $sql,
        public array $columns,
    ) {
    }
}
