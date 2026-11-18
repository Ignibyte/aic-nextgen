<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * A value a declaration computes (#310): a column, a literal, or arithmetic,
 * text or length over them. Only `Schema::expressions()` makes one, already
 * rendered by its dialect, so a module never writes its SQL (gate:8,
 * `aic.schemaExpression`).
 */
final readonly class Term
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
