<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Http\Application\ResponseBuilder;
use AIC\OAuth\Domain\OAuthServer;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

use function is_string;
use function json_encode;
use function strlen;
use function substr;

use const JSON_UNESCAPED_SLASHES;

/**
 * The two documents a client discovers the server by (#331): the
 * authorization server's metadata (RFC 8414) and the protected resource's
 * (RFC 9728), the latter also at a resource's path after the well-known one
 * (`/.well-known/oauth-protected-resource/mcp` for `/mcp`). Both come from
 * the config alone.
 */
final readonly class OAuthMetadata
{
    /**
     * @param OAuthServer     $oauthServer     The server's settings.
     * @param ResponseBuilder $responseBuilder Makes the answers.
     */
    public function __construct(
        private OAuthServer $oauthServer,
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface The authorization server's metadata.
     */
    public function server(): ResponseInterface
    {
        $issuer = self::json($this->oauthServer->url(''));

        return $this->document(
            '{"issuer":' . $issuer . ',"authorization_endpoint":'
                . self::json($this->oauthServer->url($this->oauthServer->authorizationPath))
                . ',"token_endpoint":' . self::json($this->oauthServer->url(OAuthServer::TOKEN_PATH))
                . ',"response_types_supported":["code"],"grant_types_supported":["authorization_code","client_credentials"]'
                . ',"token_endpoint_auth_methods_supported":["client_secret_basic","client_secret_post","none"]'
                . ',"code_challenge_methods_supported":["S256"]}',
        );
    }

    /**
     * @param string $path The request's path: the well-known path, perhaps with a resource's path after it.
     *
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface The protected resource's metadata.
     */
    public function resource(string $path): ResponseInterface
    {
        $resource = substr($path, strlen(OAuthServer::RESOURCE_METADATA_PATH));

        return $this->document(
            '{"resource":' . self::json($this->oauthServer->url($resource)) . ',"authorization_servers":['
                . self::json($this->oauthServer->url('')) . '],"bearer_methods_supported":["header"]}',
        );
    }

    /**
     * @param string $json The document.
     *
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface 200 with it.
     */
    private function document(string $json): ResponseInterface
    {
        return $this->responseBuilder->withHeader(
            $this->responseBuilder->create(200, 'application/json', $json),
            'Cache-Control',
            'public, max-age=3600',
        );
    }

    /**
     * @param string $text Text.
     *
     * @return string It as a JSON string.
     */
    private static function json(string $text): string
    {
        $json = json_encode($text, JSON_UNESCAPED_SLASHES);

        return is_string($json)
            ? $json
            : '""';
    }
}
