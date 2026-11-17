<?php

declare(strict_types=1);

namespace {namespace}\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `{table}` pivot between `{owner}` and `{target}`, made by bin/aic
 * make:relation (#306): each row links one `{owner}` row to one `{target}`
 * row, once, with fields of its own; deleting either row deletes the link.
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
            ->identity('id')
            {ownerColumn}
            {targetColumn}{columns}
            ->primary(['id'])
            ->unique(['{ownerKey}', '{targetKey}'])
            ->foreignKey('{ownerKey}', '{owner}', 'id', OnDelete::Cascade)
            ->foreignKey('{targetKey}', '{target}', 'id', OnDelete::Cascade);
    }
}
