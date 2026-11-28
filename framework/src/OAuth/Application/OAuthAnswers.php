<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Http\Application\ResponseBuilder;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\IssuedToken;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

use function implode;
use function is_string;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * What the authorization server's endpoints answer (#331, shared since #352):
 * a JSON document or an empty body, never cached (RFC 6749 section 5.1); an
 * issued token (section 5.1, with its refresh token when it has one); a
 * refusal (section 5.2), `invalid_client` as 401 with a Basic challenge and
 * `invalid_token` as 401 with a Bearer one; and a 405 for a method an
 * endpoint does not take.
 */
final readonly class OAuthAnswers
{
    /**
     * @param ResponseBuilder $responseBuilder Makes the answers.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param int    $status The status.
     * @param string $json   The document.
     *
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface The document, never cached.
     */
    public function document(int $status, string $json): ResponseInterface
    {
        return $this->uncached($this->responseBuilder->create($status, 'application/json', $json));
    }

    /**
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface A 200 with no body, never cached.
     */
    public function done(): ResponseInterface
    {
        return $this->uncached($this->responseBuilder->create(200, 'text/plain; charset=utf-8', ''));
    }

    /**
     * @param IssuedToken $issuedToken The token.
     *
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface RFC 6749's token answer.
     */
    public function issued(IssuedToken $issuedToken): ResponseInterface
    {
        $refresh = $issuedToken->refresh === ''
            ? ''
            : ',"refresh_token":' . $this->text($issuedToken->refresh);

        return $this->document(
            200,
            '{"access_token":' . $this->text($issuedToken->value) . ',"token_type":"Bearer","expires_in":'
                . $issuedToken->seconds . ',"scope":' . $this->text(implode(' ', $issuedToken->scopes)) . $refresh . '}',
        );
    }

    /**
     * @param OAuthRefusedException $oauthRefusedException The refusal.
     *
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface Its error document, with the challenge a 401 needs.
     */
    public function refused(OAuthRefusedException $oauthRefusedException): ResponseInterface
    {
        $response = $this->document(
            $oauthRefusedException->status,
            '{"error":' . $this->text($oauthRefusedException->error) . ',"error_description":'
                . $this->text($oauthRefusedException->description) . '}',
        );

        if ($oauthRefusedException->status !== 401) {
            return $response;
        }

        return $this->responseBuilder->withHeader(
            $response,
            'WWW-Authenticate',
            $oauthRefusedException->error === 'invalid_token'
                ? 'Bearer error="invalid_token"'
                : 'Basic realm="oauth"',
        );
    }

    /**
     * @param string $endpoint What the endpoint is, for the message.
     *
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface The 405, naming POST.
     */
    public function postOnly(string $endpoint): ResponseInterface
    {
        $response = $this->responseBuilder->create(405, 'text/plain; charset=utf-8', 'The ' . $endpoint . " takes POST.\n");

        return $this->responseBuilder->withHeader($response, 'Allow', 'POST');
    }

    /**
     * @param string $text A string.
     *
     * @return string It as a JSON string.
     */
    public function text(string $text): string
    {
        $json = json_encode($text, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json)
            ? $json
            : '""';
    }

    /**
     * @param list<string> $texts Strings.
     *
     * @return string They as a JSON list.
     */
    public function texts(array $texts): string
    {
        $json = [];

        foreach ($texts as $text) {
            $json[] = $this->text($text);
        }

        return '[' . implode(',', $json) . ']';
    }

    /**
     * @param ResponseInterface $response An answer.
     *
     * @throws InvalidArgumentException When a header cannot be set.
     *
     * @return ResponseInterface It with `Cache-Control: no-store` and `Pragma: no-cache`.
     */
    private function uncached(ResponseInterface $response): ResponseInterface
    {
        return $this->responseBuilder->withHeader(
            $this->responseBuilder->withHeader($response, 'Cache-Control', 'no-store'),
            'Pragma',
            'no-cache',
        );
    }
}
