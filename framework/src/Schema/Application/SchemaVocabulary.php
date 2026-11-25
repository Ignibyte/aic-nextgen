<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\Defaults;
use AIC\Schema\Contracts\Expressions;

/**
 * What a migration declares with (#339): the column types, the terms and
 * predicates (#310) and the common defaults, Postgres's, made once for a
 * `SchemaPlan`.
 */
final readonly class SchemaVocabulary
{
    /**
     * @param ColumnTypes $columnTypes The column types.
     * @param Expressions $expressions The terms and predicates.
     * @param Defaults    $defaults    The common defaults.
     */
    public function __construct(
        public ColumnTypes $columnTypes = new ColumnTypeFactory(),
        public Expressions $expressions = new PostgresExpressions(),
        public Defaults $defaults = new PostgresDefaults(),
    ) {
    }
}
