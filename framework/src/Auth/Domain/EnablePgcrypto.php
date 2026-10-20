<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Enables Postgres's pgcrypto, which PgcryptoPasswordHasher hashes with: an
 * app that signs users in registers this migration with its own.
 */
final readonly class EnablePgcrypto implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_20_000002_enable_pgcrypto';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->extension('pgcrypto');
    }
}
