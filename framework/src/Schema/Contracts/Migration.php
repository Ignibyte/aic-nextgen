<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * One step of the schema, applied once and recorded in the ledger. Migrations
 * run forward only, in the order the app lists them.
 */
interface Migration
{
    /**
     * @return string The version the ledger records it under, unique and stable.
     */
    public function version(): string;

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    public function describe(Schema $schema): void;
}
