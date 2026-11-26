<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\AuthorizationRequest;
use AIC\OAuth\Domain\CodeGrant;
use AIC\OAuth\Domain\OAuthServer;
use AIC\Session\Domain\RandomTokens;
use SensitiveParameter;

use function array_filter;
use function array_values;
use function base64_encode;
use function explode;
use function hash;
use function hash_equals;
use function implode;
use function preg_match;
use function rtrim;
use function strtr;

/**
 * Authorization codes in Postgres (#331): one is issued when a person allows
 * a request, kept as its SHA-256 with the client, the redirect URI, the scopes
 * and the PKCE challenge, and lives `codeSeconds`. Redeeming takes it out in
 * the same statement that reads it, so a code works once, and then checks the
 * client, the redirect URI, the expiry and the verifier (S256, RFC 7636).
 */
final readonly class AuthorizationCodes
{
    /**
     * A PKCE code verifier: 43 to 128 unreserved characters (RFC 7636 section 4.1).
     */
    private const string VERIFIER = '/^[A-Za-z0-9._~-]{43,128}$/';

    /**
     * @param Database     $database     The connection.
     * @param RandomTokens $randomTokens Makes the codes.
     */
    public function __construct(
        private Database $database,
        private RandomTokens $randomTokens = new RandomTokens(),
    ) {
    }

    /**
     * @param AuthorizationRequest $authorizationRequest The request allowed.
     * @param string               $userId               The person who allowed it.
     * @param list<string>         $scopes               The scopes they allowed.
     * @param OAuthServer          $oauthServer          How long a code lives.
     *
     * @throws QueryException When the row cannot be written.
     *
     * @return string The code, which is not kept.
     */
    public function issue(
        AuthorizationRequest $authorizationRequest,
        string $userId,
        array $scopes,
        OAuthServer $oauthServer,
    ): string {
        $code = $this->randomTokens->fresh();
        $this->database->execute('DELETE FROM aic_oauth_codes WHERE expires_at < now()', []);
        $this->database->execute(
            'INSERT INTO aic_oauth_codes (code_hash, client_id, user_id, redirect_uri, scopes, challenge, expires_at)'
                . " VALUES (?, ?, ?, ?, ?, ?, now() + (? || ' seconds')::interval)",
            [
                hash('sha256', $code),
                $authorizationRequest->clientId,
                $userId,
                $authorizationRequest->redirectUri,
                implode(' ', $scopes),
                $authorizationRequest->challenge,
                (string) $oauthServer->codeSeconds,
            ],
        );

        return $code;
    }

    /**
     * @param string $code        The code a token request sent.
     * @param string $clientId    The client that sent it.
     * @param string $redirectUri The redirect URI it names.
     * @param string $verifier    Its PKCE code verifier.
     *
     * @throws QueryException        When the table cannot be read.
     * @throws OAuthRefusedException When the code is unknown, used, expired, another client's or another
     *                               redirect URI's, or the verifier does not match its challenge (`invalid_grant`).
     *
     * @return CodeGrant What it grants.
     */
    public function redeem(#[SensitiveParameter]
        string $code, string $clientId, string $redirectUri, #[SensitiveParameter]
        string $verifier, ): CodeGrant
    {
        $row = $this->taken($code);

        if (!$row instanceof Row || !self::redeems($row, $clientId, $redirectUri, $verifier)) {
            throw new OAuthRefusedException(
                'invalid_grant',
                'The code is not known, has been used or has expired, or was issued for another request.',
            );
        }

        $scopes = array_filter(explode(' ', $row->string('scopes')), static fn (string $scope): bool => $scope !== '');

        return new CodeGrant($row->string('user_id'), array_values($scopes));
    }

    /**
     * @param string $code A code a token request sent.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return Row|null Its row, taken out of the table so it works once; null for an unknown code.
     */
    private function taken(#[SensitiveParameter]
        string $code, ): ?Row
    {
        if ($code === '') {
            return null;
        }

        return $this->database->fetchAll(
            'DELETE FROM aic_oauth_codes WHERE code_hash = ? RETURNING client_id, user_id, redirect_uri, scopes, challenge,'
                . " CASE WHEN expires_at > now() THEN 'live' ELSE '' END AS live",
            [hash('sha256', $code)],
        )->first();
    }

    /**
     * @param string $verifier A PKCE code verifier.
     *
     * @return string Its S256 challenge: the base64url SHA-256, unpadded.
     */
    public static function challenge(#[SensitiveParameter]
        string $verifier, ): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * @param Row    $row         The code's row, as it was taken out.
     * @param string $clientId    The client that sent it.
     * @param string $redirectUri The redirect URI it names.
     * @param string $verifier    Its PKCE code verifier.
     *
     * @throws QueryException When a column is missing.
     *
     * @return bool Whether the code was live, the client's and the redirect URI's, and the verifier its own.
     */
    private static function redeems(Row $row, string $clientId, string $redirectUri, #[SensitiveParameter]
        string $verifier, ): bool
    {
        return $row->string('live') !== ''
            && $row->string('client_id') === $clientId
            && $row->string('redirect_uri') === $redirectUri
            && preg_match(self::VERIFIER, $verifier) === 1
            && hash_equals($row->string('challenge'), self::challenge($verifier));
    }
}
