<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\OAuth\Domain\IssuedToken;
use AIC\OAuth\Domain\OAuthServer;
use AIC\OAuth\Domain\TokenStanding;
use AIC\Session\Domain\RandomTokens;
use SensitiveParameter;

use function array_filter;
use function array_values;
use function explode;
use function hash;
use function implode;

/**
 * The access tokens the authorization server issues (#331): opaque random
 * values handed to the client once and kept only as their SHA-256 in
 * `aic_oauth_tokens`, with the client, the person they act for, the scopes
 * granted and when they expire, and since #352 the refresh family they
 * belong to and the path they are bound to. `BearerTokens` reads them as it
 * reads the operator's tokens.
 */
final readonly class AccessTokens
{
    /**
     * @param Database     $database     Where tokens are kept.
     * @param RandomTokens $randomTokens Makes each value.
     */
    public function __construct(
        private Database $database,
        private RandomTokens $randomTokens = new RandomTokens(),
    ) {
    }

    /**
     * @param string       $clientId    The client it is issued to.
     * @param string       $userId      The person it acts for; empty for the client's own.
     * @param list<string> $scopes      What it holds.
     * @param OAuthServer  $oauthServer The server, for the lifetime.
     * @param string       $family      The refresh family it belongs to; empty for none.
     * @param string       $resource    The path it is bound to; empty for none.
     *
     * @throws QueryException When it cannot be written.
     *
     * @return IssuedToken The token, which is not kept.
     */
    public function issue(
        string $clientId,
        string $userId,
        array $scopes,
        OAuthServer $oauthServer,
        string $family = '',
        string $resource = '',
    ): IssuedToken {
        $value = $this->randomTokens->fresh();
        $this->database->execute('DELETE FROM aic_oauth_tokens WHERE expires_at < now()', []);
        $this->database->execute(
            'INSERT INTO aic_oauth_tokens (token_hash, client_id, user_id, scopes, family, resource, expires_at)'
                . " VALUES (?, ?, ?, ?, ?, ?, now() + (? || ' seconds')::interval)",
            [
                hash('sha256', $value),
                $clientId,
                $userId,
                implode(' ', $scopes),
                $family,
                $resource,
                (string) $oauthServer->tokenSeconds,
            ],
        );

        return new IssuedToken($value, $oauthServer->tokenSeconds, $scopes);
    }

    /**
     * @param string $token    A token.
     * @param string $clientId The client giving it back.
     *
     * @throws QueryException When it cannot be removed.
     */
    public function revoke(#[SensitiveParameter]
        string $token, string $clientId, ): void
    {
        $this->database->execute(
            'DELETE FROM aic_oauth_tokens WHERE token_hash = ? AND client_id = ?',
            [hash('sha256', $token), $clientId],
        );
    }

    /**
     * @param string $family A refresh family.
     *
     * @throws QueryException When its tokens cannot be removed.
     */
    public function revokeFamily(string $family): void
    {
        $this->database->execute("DELETE FROM aic_oauth_tokens WHERE family = ? AND family <> ''", [$family]);
    }

    /**
     * @param string $token A token.
     *
     * @throws QueryException When the tokens cannot be read.
     *
     * @return TokenStanding|null The token while it is live; null otherwise.
     */
    public function standing(#[SensitiveParameter]
        string $token, ): ?TokenStanding
    {
        $row = $this->database->fetchAll(
            'SELECT client_id, user_id, scopes, resource, extract(epoch FROM expires_at)::bigint AS expires'
                . ' FROM aic_oauth_tokens WHERE token_hash = ? AND expires_at > now()',
            [hash('sha256', $token)],
        )->first();

        return $row instanceof Row
            ? $this->standingOf($row, false)
            : null;
    }

    /**
     * @param Row  $row     A token's row.
     * @param bool $refresh Whether it is a refresh token.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return TokenStanding What it holds.
     */
    public function standingOf(Row $row, bool $refresh): TokenStanding
    {
        $scopes = array_filter(explode(' ', $row->string('scopes')), static fn (string $scope): bool => $scope !== '');

        return new TokenStanding(
            $row->string('client_id'),
            $row->string('user_id'),
            array_values($scopes),
            $row->string('resource'),
            $row->int('expires'),
            $refresh,
        );
    }
}
