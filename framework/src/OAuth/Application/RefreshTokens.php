<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\CodeGrant;
use AIC\OAuth\Domain\OAuthServer;
use AIC\OAuth\Domain\TokenStanding;
use AIC\Session\Domain\RandomTokens;
use SensitiveParameter;

use function hash;
use function implode;

/**
 * The refresh tokens the authorization server issues with a code-flow
 * access token (RFC 6749 section 6, #352), kept as their SHA-256 in
 * `aic_oauth_refresh_tokens`. Each belongs to a family, the code exchange
 * it descends from. A refresh spends the token in one guarded UPDATE and the
 * caller issues the next one in the same family; a spent token presented
 * again means it leaked, so its whole family goes, refresh and access tokens
 * alike (RFC 9700 section 4.14). A token lives `refreshSeconds` from its
 * issue, so a family in use keeps going and an idle one ends.
 */
final readonly class RefreshTokens
{
    /**
     * @param Database     $database     Where refresh tokens are kept.
     * @param AccessTokens $accessTokens The access tokens a revoked family takes with it.
     * @param RandomTokens $randomTokens Makes each value and family.
     */
    public function __construct(
        private Database $database,
        private AccessTokens $accessTokens,
        private RandomTokens $randomTokens = new RandomTokens(),
    ) {
    }

    /**
     * @return string A new family.
     */
    public function family(): string
    {
        return $this->randomTokens->fresh();
    }

    /**
     * @param string      $clientId    The client it is issued to.
     * @param CodeGrant   $codeGrant   What it grants, and its family.
     * @param OAuthServer $oauthServer The server, for the lifetime.
     *
     * @throws QueryException When it cannot be written.
     *
     * @return string The token, which is not kept.
     */
    public function issue(string $clientId, CodeGrant $codeGrant, OAuthServer $oauthServer): string
    {
        $value = $this->randomTokens->fresh();
        $this->database->execute('DELETE FROM aic_oauth_refresh_tokens WHERE expires_at < now()', []);
        $this->database->execute(
            'INSERT INTO aic_oauth_refresh_tokens (token_hash, family, client_id, user_id, scopes, resource, expires_at)'
                . " VALUES (?, ?, ?, ?, ?, ?, now() + (? || ' seconds')::interval)",
            [
                hash('sha256', $value),
                $codeGrant->family,
                $clientId,
                $codeGrant->userId,
                implode(' ', $codeGrant->scopes),
                $codeGrant->resource,
                (string) $oauthServer->refreshSeconds,
            ],
        );

        return $value;
    }

    /**
     * @param string $token    The refresh token presented.
     * @param string $clientId The client presenting it.
     *
     * @throws OAuthRefusedException When it is unknown, expired, another client's or spent (`invalid_grant`); a
     *                               spent one has revoked its family first.
     * @throws QueryException        When the tokens cannot be read or written.
     *
     * @return CodeGrant What it granted, with its family.
     */
    public function spend(#[SensitiveParameter]
        string $token, string $clientId, ): CodeGrant
    {
        $row = $token === ''
            ? null
            : $this->database->fetchAll(
                'UPDATE aic_oauth_refresh_tokens SET used = true WHERE token_hash = ? AND client_id = ? AND NOT used'
                    . ' AND expires_at > now() RETURNING family, client_id, user_id, scopes, resource,'
                    . ' extract(epoch FROM expires_at)::bigint AS expires',
                [hash('sha256', $token), $clientId],
            )->first();

        if (!$row instanceof Row) {
            $this->reused($token);

            throw new OAuthRefusedException('invalid_grant', 'The refresh token is not known, has expired or has been used.');
        }

        $tokenStanding = $this->accessTokens->standingOf($row, true);

        return new CodeGrant($tokenStanding->userId, $tokenStanding->scopes, $tokenStanding->resource, $row->string('family'));
    }

    /**
     * @param string $token    A refresh token.
     * @param string $clientId The client giving it back.
     *
     * @throws QueryException When the tokens cannot be read or removed.
     *
     * @return bool Whether it was that client's refresh token, and its family went.
     */
    public function revoke(#[SensitiveParameter]
        string $token, string $clientId, ): bool
    {
        $row = $this->database->fetchAll(
            'SELECT family FROM aic_oauth_refresh_tokens WHERE token_hash = ? AND client_id = ?',
            [hash('sha256', $token), $clientId],
        )->first();

        if (!$row instanceof Row) {
            return false;
        }

        $this->revokeFamily($row->string('family'));

        return true;
    }

    /**
     * @param string $token A refresh token.
     *
     * @throws QueryException When the tokens cannot be read.
     *
     * @return TokenStanding|null The token while it is live and unspent; null otherwise.
     */
    public function standing(#[SensitiveParameter]
        string $token, ): ?TokenStanding
    {
        $row = $this->database->fetchAll(
            'SELECT client_id, user_id, scopes, resource, extract(epoch FROM expires_at)::bigint AS expires'
                . ' FROM aic_oauth_refresh_tokens WHERE token_hash = ? AND NOT used AND expires_at > now()',
            [hash('sha256', $token)],
        )->first();

        return $row instanceof Row
            ? $this->accessTokens->standingOf($row, true)
            : null;
    }

    /**
     * @param string $token A refresh token that could not be spent.
     *
     * @throws QueryException When the tokens cannot be read or removed.
     */
    private function reused(#[SensitiveParameter]
        string $token, ): void
    {
        $spent = $this->database->fetchAll(
            'SELECT family FROM aic_oauth_refresh_tokens WHERE token_hash = ? AND used',
            [hash('sha256', $token)],
        )->first();

        if (!($spent instanceof Row)) {
            return;
        }

        $this->revokeFamily($spent->string('family'));
    }

    /**
     * @param string $family A family.
     *
     * @throws QueryException When its tokens cannot be removed.
     */
    private function revokeFamily(string $family): void
    {
        $this->database->execute('DELETE FROM aic_oauth_refresh_tokens WHERE family = ?', [$family]);
        $this->accessTokens->revokeFamily($family);
    }
}
