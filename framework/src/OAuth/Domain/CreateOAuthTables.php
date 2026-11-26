<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The authorization server's tables (#331): `aic_oauth_clients`, each client
 * the operator registered, with the SHA-256 of its secret (empty for a public
 * client), the scopes it may hold and its redirect URIs, space-separated;
 * `aic_oauth_codes`, each authorization code by its SHA-256 with what it
 * grants and its PKCE challenge; and `aic_oauth_tokens`, each access token by
 * its SHA-256 with its client, the person it acts for (empty for a client's
 * own) and its scopes. A code and a token keep when they expire.
 */
final readonly class CreateOAuthTables implements Migration
{
    /**
     * @return string The version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_26_000001_create_aic_oauth_tables';
    }

    /**
     * @param Schema $schema The schema.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_oauth_clients')
            ->string('id', 64)
            ->string('secret_hash', 64)
            ->text('scopes')
            ->text('redirect_uris')
            ->timestampTz('created_at')
            ->primary(['id']);
        self::codes($schema);
        $schema->create('aic_oauth_tokens')
            ->string('token_hash', 64)
            ->string('client_id', 64)
            ->text('user_id')
            ->text('scopes')
            ->timestampTz('expires_at')
            ->primary(['token_hash']);
    }

    /**
     * @param Schema $schema The schema, to create the codes' table on.
     */
    private static function codes(Schema $schema): void
    {
        $schema->create('aic_oauth_codes')
            ->string('code_hash', 64)
            ->string('client_id', 64)
            ->text('user_id')
            ->text('redirect_uri')
            ->text('scopes')
            ->string('challenge', 64)
            ->timestampTz('expires_at')
            ->primary(['code_hash']);
    }
}
