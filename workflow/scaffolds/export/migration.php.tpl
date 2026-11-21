<?php

declare(strict_types=1);

namespace {namespace}\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The exports' table `{table}` and its view `{states}`, made by
 * bin/aic make:export (#323): each export's uuid and when it was asked
 * for, and whether {days} days have passed since by the database's `now()`.
 */
final readonly class {class} implements Migration
{
    /**
     * Each export, and whether it is past its days.
     */
    private const string STATES = "SELECT id, (created_at < now() - interval '{days} days') AS expired FROM {table}";

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
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('{table}')
            ->column('id', $schema->types()->uuidKey())
            ->timestampTz('created_at')
            ->primary(['id']);
        $schema->view('{states}', self::STATES);
    }
}
