<?php

declare(strict_types=1);

namespace AIC\Access\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The bearer tokens' table (#313): `aic_api_tokens`, each token by its
 * name, with the SHA-256 of its value (never the value) and its scopes,
 * the permissions it holds, space-separated as OAuth writes them.
 */
final readonly class CreateTokensTable implements Migration
{
    /**
     * @return string The version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_000003_create_aic_api_tokens';
    }

    /**
     * @param Schema $schema The schema.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_api_tokens')
            ->string('name', 64)
            ->string('token_hash', 64)
            ->text('scopes')
            ->timestampTz('created_at')
            ->primary(['name'])
            ->unique(['token_hash']);
    }
}
