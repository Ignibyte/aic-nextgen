<?php

declare(strict_types=1);

namespace AIC\Cache\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The cache's tables (#312), both unlogged, so a crash empties them:
 * `aic_cache` (a value by key until it expires) and `aic_rate_limits` (a
 * client's hits on a limit in its open window).
 */
final readonly class CreateCacheTables implements Migration
{
    /**
     * @return string The version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_000002_create_aic_cache';
    }

    /**
     * @param Schema $schema The schema.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_cache')
            ->unlogged()
            ->string('key', 200)
            ->text('value')
            ->timestampTz('expires_at')
            ->primary(['key'])
            ->index(['expires_at']);
        $schema->create('aic_rate_limits')
            ->unlogged()
            ->string('name', 200)
            ->string('client', 128)
            ->integer('hits')
            ->timestampTz('window_ends_at')
            ->primary(['name', 'client'])
            ->index(['window_ends_at']);
    }
}
