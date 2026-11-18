<?php

declare(strict_types=1);

namespace {namespace}\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The `{field}` state of `{table}`, made by bin/aic make:state (#308): a
 * choice of its states, every row, old or new, starting in `{initial}`.
 */
final readonly class {class} implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '{version}';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When the column is declared wrongly.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $choice = $columnTypes->choice([{choices}]);
        $term = $schema->expressions()->text('{initial}');
        $schema->alter('{table}')->addColumn('{field}', $columnTypes->defaulted($choice, $term));
    }
}
