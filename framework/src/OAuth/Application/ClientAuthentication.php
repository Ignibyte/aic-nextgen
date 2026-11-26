<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\OAuthClient;
use Psr\Http\Message\ServerRequestInterface;

use function base64_decode;
use function count;
use function explode;
use function is_string;
use function str_starts_with;
use function strtolower;
use function substr;
use function urldecode;

/**
 * Finds which client sent a token request (#331), by HTTP Basic
 * (`client_secret_basic`, the id and the secret form-encoded, RFC 6749
 * section 2.3.1), by `client_id` and `client_secret` in the body
 * (`client_secret_post`), or by `client_id` alone for a public client
 * (`none`). Both ways at once, or neither, is refused.
 */
final readonly class ClientAuthentication
{
    /**
     * @param OAuthClients $oauthClients The registered clients.
     */
    public function __construct(
        private OAuthClients $oauthClients,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The token request.
     * @param array<string, string>  $fields        Its body's fields.
     *
     * @throws QueryException        When the clients cannot be read.
     * @throws OAuthRefusedException When the request names no client, two ways, or one whose secret does not match
     *                               (`invalid_client`, 401).
     *
     * @return OAuthClient The client.
     */
    public function client(ServerRequestInterface $serverRequest, array $fields): OAuthClient
    {
        $pair = self::credentials($serverRequest, $fields);
        $client = $this->oauthClients->authenticated($pair[0], $pair[1]);

        if ($client === null) {
            throw new OAuthRefusedException('invalid_client', 'The client is not known or its secret does not match.', 401);
        }

        return $client;
    }

    /**
     * @param ServerRequestInterface $serverRequest The token request.
     * @param array<string, string>  $fields        Its body's fields.
     *
     * @throws OAuthRefusedException When the client authenticated both by Basic and in the body (`invalid_request`).
     *
     * @return array{string, string} The client id and secret it sent; empty strings for none.
     */
    private static function credentials(ServerRequestInterface $serverRequest, array $fields): array
    {
        $header = $serverRequest->getHeaderLine('Authorization');
        $bodyId = $fields['client_id'] ?? '';
        $bodySecret = $fields['client_secret'] ?? '';

        if (strtolower(substr($header, 0, 6)) !== 'basic ') {
            return [$bodyId, $bodySecret];
        }

        if ($bodyId !== '' || $bodySecret !== '') {
            throw new OAuthRefusedException('invalid_request', 'The client authenticated more than one way.');
        }

        return self::basic(substr($header, 6));
    }

    /**
     * @param string $credentials The Basic credentials, base64.
     *
     * @return array{string, string} The client id and secret; two empty strings when they do not decode.
     */
    private static function basic(string $credentials): array
    {
        $decoded = base64_decode($credentials, true);

        if (!is_string($decoded) || str_starts_with($decoded, ':')) {
            return ['', ''];
        }

        $parts = explode(':', $decoded, 2);

        return count($parts) === 2
            ? [urldecode($parts[0]), urldecode($parts[1])]
            : ['', ''];
    }
}
