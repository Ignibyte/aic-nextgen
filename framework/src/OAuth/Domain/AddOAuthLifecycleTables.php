<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * What the authorization server keeps since #352: `aic_oauth_refresh_tokens`,
 * each refresh token by its SHA-256 with its family (the code exchange it
 * descends from), client, person, scopes, bound path and whether it has been
 * spent; and on `aic_oauth_tokens` the family an access token belongs to and
 * the path it is bound to, and on `aic_oauth_codes` the path the
 * authorization asked for. An empty family or path is none.
 */
final readonly class AddOAuthLifecycleTables implements Migration
{
    /**
     * @return string The version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_27_000001_add_aic_oauth_lifecycle';
    }

    /**
     * @param Schema $schema The schema.
     *
     * @throws InvalidArgumentException When a column is declared wrongly.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $types = $schema->types();
        $columnType = $types->defaulted($types->text(), $schema->expressions()->text(''));
        $schema->alter('aic_oauth_tokens')
            ->addColumn('family', $columnType)
            ->addColumn('resource', $columnType);
        $schema->alter('aic_oauth_codes')
            ->addColumn('resource', $columnType);
        $schema->create('aic_oauth_refresh_tokens')
            ->string('token_hash', 64)
            ->string('family', 64)
            ->string('client_id', 64)
            ->text('user_id')
            ->text('scopes')
            ->text('resource')
            ->column('used', $types->defaulted($types->boolean(), $schema->expressions()->boolean(false)))
            ->timestampTz('expires_at')
            ->primary(['token_hash']);
    }
}
