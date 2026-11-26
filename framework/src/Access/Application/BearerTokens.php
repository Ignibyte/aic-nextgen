<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Access\Domain\Actor;
use AIC\Access\Domain\ApiToken;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use SensitiveParameter;

use function explode;
use function hash;
use function implode;

/**
 * Bearer tokens in Postgres (#313): the operator adds one by name with the
 * scopes it holds, and a request's token is found by its SHA-256, the only
 * form of it the table keeps. A token the authorization server issued
 * (#331) is found the same way in `aic_oauth_tokens` until it expires. A
 * scope is a permission a module declares; like a role's grant, the store
 * does not check that it is.
 */
final readonly class BearerTokens
{
    /**
     * @param Database $database The connection.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $token A request's bearer token; empty when it sent the scheme with none.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return Actor The token's caller (its name, or its OAuth client and the person it acts for) with its scopes as
     *               permissions, or a caller with neither for an unknown or expired token.
     */
    public function actor(#[SensitiveParameter]
        string $token, ): Actor
    {
        $row = $token === ''
            ? null
            : $this->database->fetchAll(
                "SELECT name, scopes, created_at::text AS created_at, '' AS user_id FROM aic_api_tokens WHERE token_hash = ?"
                    . ' UNION ALL SELECT client_id, scopes, expires_at::text, user_id FROM aic_oauth_tokens'
                    . ' WHERE token_hash = ? AND expires_at > now()',
                [hash('sha256', $token), hash('sha256', $token)],
            )->first();

        return $row instanceof Row
            ? new Actor($row->string('user_id'), self::apiToken($row)->scopes, $row->string('name'), true)
            : new Actor('', [], '', true);
    }

    /**
     * @param string       $name   The token's name.
     * @param string       $token  Its value, which is not kept.
     * @param list<string> $scopes The permissions it holds.
     *
     * @throws QueryException When the row cannot be written.
     *
     * @return bool Whether it was added; false when the name or the value is taken.
     */
    public function add(string $name, #[SensitiveParameter]
        string $token, array $scopes, ): bool
    {
        return $this->database->execute(
            'INSERT INTO aic_api_tokens (name, token_hash, scopes, created_at) VALUES (?, ?, ?, now()) ON CONFLICT DO NOTHING',
            [$name, hash('sha256', $token), implode(' ', $scopes)],
        ) > 0;
    }

    /**
     * @param string $name A token's name.
     *
     * @throws QueryException When the row cannot be removed.
     *
     * @return bool Whether a token of that name was there.
     */
    public function revoke(string $name): bool
    {
        return $this->database->execute('DELETE FROM aic_api_tokens WHERE name = ?', [$name]) > 0;
    }

    /**
     * @throws QueryException When the table cannot be read.
     *
     * @return list<ApiToken> Every token, by name.
     */
    public function all(): array
    {
        $apiTokens = [];
        $rows = $this->database->fetchAll(
            'SELECT name, scopes, created_at::text AS created_at FROM aic_api_tokens ORDER BY name',
            [],
        );

        foreach ($rows as $row) {
            $apiTokens[] = self::apiToken($row);
        }

        return $apiTokens;
    }

    /**
     * @param Row $row A row of the table.
     *
     * @throws QueryException When a column is missing.
     *
     * @return ApiToken The token it describes.
     */
    private static function apiToken(Row $row): ApiToken
    {
        $scopes = [];

        foreach (explode(' ', $row->string('scopes')) as $scope) {
            if ($scope === '') {
                continue;
            }

            $scopes[] = $scope;
        }

        return new ApiToken($row->string('name'), $scopes, $row->string('created_at'));
    }
}
