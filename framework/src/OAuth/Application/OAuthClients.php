<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\OAuth\Domain\OAuthClient;
use SensitiveParameter;

use function explode;
use function hash;
use function hash_equals;
use function implode;

/**
 * The registered clients in Postgres (#331): the operator adds one with its
 * secret (or none, for a public client), its scopes and its redirect URIs, and
 * the token endpoint finds it by id. Only the secret's SHA-256 is kept, and
 * a secret is compared in constant time. Revoking a client removes its codes
 * and tokens with it.
 */
final readonly class OAuthClients
{
    /**
     * The columns a client is read from.
     */
    private const string SELECT = 'SELECT id, secret_hash, scopes, redirect_uris, created_at::text AS created_at,'
        . ' (SELECT count(*) FROM aic_oauth_tokens WHERE client_id = aic_oauth_clients.id AND expires_at > now())'
        . ' AS live_tokens FROM aic_oauth_clients';

    /**
     * @param Database $database The connection.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $clientId A client id.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return OAuthClient|null The client; null when none has the id.
     */
    public function client(string $clientId): ?OAuthClient
    {
        $row = $this->row($clientId);

        return $row instanceof Row
            ? self::oauthClient($row)
            : null;
    }

    /**
     * @param string $clientId The id a request names.
     * @param string $secret   The secret it sent; empty for a public client's request.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return OAuthClient|null The client when the secret is its own, or when a public client sent none; null
     *                          otherwise.
     */
    public function authenticated(string $clientId, #[SensitiveParameter]
        string $secret, ): ?OAuthClient
    {
        $row = $this->row($clientId);

        if (!$row instanceof Row) {
            return null;
        }

        $stored = $row->string('secret_hash');
        $matches = $stored === ''
            ? $secret === ''
            : $secret !== '' && hash_equals($stored, hash('sha256', $secret));

        return $matches
            ? self::oauthClient($row)
            : null;
    }

    /**
     * @param string       $clientId     The client's id.
     * @param string       $secret       Its secret, which is not kept; empty for a public client.
     * @param list<string> $scopes       The scopes it may hold.
     * @param list<string> $redirectUris Where an authorization may return to.
     *
     * @throws QueryException When the row cannot be written.
     *
     * @return bool Whether it was added; false when the id is taken.
     */
    public function add(string $clientId, #[SensitiveParameter]
        string $secret, array $scopes, array $redirectUris, ): bool
    {
        return $this->database->execute(
            'INSERT INTO aic_oauth_clients (id, secret_hash, scopes, redirect_uris, created_at) VALUES (?, ?, ?, ?, now())'
                . ' ON CONFLICT DO NOTHING',
            [$clientId, $secret === '' ? '' : hash('sha256', $secret), implode(' ', $scopes), implode(' ', $redirectUris)],
        ) > 0;
    }

    /**
     * @param string $clientId A client's id.
     *
     * @throws QueryException When the rows cannot be removed.
     *
     * @return bool Whether a client of that id was there; its codes, tokens and refresh tokens go with it.
     */
    public function revoke(string $clientId): bool
    {
        return $this->database->execute(
            'WITH codes AS (DELETE FROM aic_oauth_codes WHERE client_id = ?), tokens AS (DELETE FROM aic_oauth_tokens WHERE'
                . ' client_id = ?), refreshes AS (DELETE FROM aic_oauth_refresh_tokens WHERE client_id = ?)'
                . ' DELETE FROM aic_oauth_clients WHERE id = ?',
            [$clientId, $clientId, $clientId, $clientId],
        ) > 0;
    }

    /**
     * @throws QueryException When the table cannot be read.
     *
     * @return list<OAuthClient> Every client, by id.
     */
    public function all(): array
    {
        $clients = [];

        foreach ($this->database->fetchAll(self::SELECT . ' ORDER BY id', []) as $row) {
            $clients[] = self::oauthClient($row);
        }

        return $clients;
    }

    /**
     * @param string $clientId A client id.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return Row|null Its row; null for none.
     */
    private function row(string $clientId): ?Row
    {
        return $this->database->fetchAll(self::SELECT . ' WHERE id = ?', [$clientId])->first();
    }

    /**
     * @param Row $row A row of the table.
     *
     * @throws QueryException When a column is missing.
     *
     * @return OAuthClient The client it describes.
     */
    private static function oauthClient(Row $row): OAuthClient
    {
        return new OAuthClient(
            $row->string('id'),
            $row->string('secret_hash') !== '',
            self::words($row->string('scopes')),
            self::words($row->string('redirect_uris')),
            $row->string('created_at'),
            $row->int('live_tokens'),
        );
    }

    /**
     * @param string $text Space-separated words.
     *
     * @return list<string> Each word.
     */
    private static function words(string $text): array
    {
        $words = [];

        foreach (explode(' ', $text) as $word) {
            if ($word === '') {
                continue;
            }

            $words[] = $word;
        }

        return $words;
    }
}
