<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Domain\IssuedToken;
use AIC\OAuth\Domain\OAuthServer;
use AIC\Session\Domain\RandomTokens;

use function hash;
use function implode;

/**
 * The access tokens the token endpoint issues (#331), in `aic_oauth_tokens`:
 * a fresh random value, kept as its SHA-256 with its client, the person it
 * acts for (empty for a client's own) and its scopes, living `tokenSeconds`.
 * `BearerTokens` reads them beside the operator's; an expired one is not
 * known. Issuing clears the expired ones.
 */
final readonly class AccessTokens
{
    /**
     * @param Database     $database     The connection.
     * @param RandomTokens $randomTokens Makes the values.
     */
    public function __construct(
        private Database $database,
        private RandomTokens $randomTokens = new RandomTokens(),
    ) {
    }

    /**
     * @param string       $clientId    The client it is issued to.
     * @param string       $userId      The person it acts for; empty for the client itself.
     * @param list<string> $scopes      What it holds.
     * @param OAuthServer  $oauthServer How long it lives.
     *
     * @throws QueryException When the row cannot be written.
     *
     * @return IssuedToken The token.
     */
    public function issue(string $clientId, string $userId, array $scopes, OAuthServer $oauthServer): IssuedToken
    {
        $value = $this->randomTokens->fresh();
        $this->database->execute('DELETE FROM aic_oauth_tokens WHERE expires_at < now()', []);
        $this->database->execute(
            'INSERT INTO aic_oauth_tokens (token_hash, client_id, user_id, scopes, expires_at)'
                . " VALUES (?, ?, ?, ?, now() + (? || ' seconds')::interval)",
            [hash('sha256', $value), $clientId, $userId, implode(' ', $scopes), (string) $oauthServer->tokenSeconds],
        );

        return new IssuedToken($value, $oauthServer->tokenSeconds, $scopes);
    }
}
