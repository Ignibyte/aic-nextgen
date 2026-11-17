<?php

declare(strict_types=1);

namespace {namespace}\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `{table}` table, made by bin/aic {made}.
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
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('{table}')
            {key}{columns}
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
