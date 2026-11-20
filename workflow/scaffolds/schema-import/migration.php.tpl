<?php

declare(strict_types=1);

namespace {namespace}\Migration;

{uses}

/**
 * The `{table}` table, made by bin/aic schema:import (#318) from a pg_dump.
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
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
{describe}
    }
{methods}}
