<?php

declare(strict_types=1);

namespace AIC\Auth\Application;

use AIC\Auth\Contracts\SignInRefusedException;
use AIC\Auth\Domain\OidcProvider;
use AIC\Session\Domain\StringMapJson;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SensitiveParameter;

use function base64_encode;
use function rawurlencode;

/**
 * The authorization code exchanged at a provider's token endpoint (#313),
 * through the app's PSR-18 client: the code, the redirect URI and the PKCE
 * verifier, the client authenticated by HTTP Basic (or by its id alone, for
 * a public client). The answer's `id_token` is what the sign-in reads.
 */
final readonly class CodeExchange
{
    /**
     * @param ClientInterface         $client         The app's PSR-18 client.
     * @param RequestFactoryInterface $requestFactory Makes the request.
     * @param StreamFactoryInterface  $streamFactory  Makes its body.
     * @param StringMapJson           $stringMapJson  Reads the token endpoint's JSON.
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private StringMapJson $stringMapJson = new StringMapJson(),
    ) {
    }

    /**
     * @param OidcProvider $oidcProvider The provider.
     * @param string       $code         The code the callback carried.
     * @param string       $redirectUri  The redirect URI the sign-in sent.
     * @param string       $verifier     The PKCE verifier the sign-in kept.
     *
     * @throws SignInRefusedException   When the provider cannot be reached, refuses or sends no id token.
     * @throws InvalidArgumentException When the request cannot be made.
     *
     * @return string The id token.
     */
    public function idToken(
        OidcProvider $oidcProvider,
        #[SensitiveParameter]
        string $code,
        string $redirectUri,
        #[SensitiveParameter]
        string $verifier,
    ): string {
        $body = 'grant_type=authorization_code&code=' . rawurlencode($code) . '&redirect_uri=' . rawurlencode($redirectUri)
            . '&code_verifier=' . rawurlencode($verifier)
            . ($oidcProvider->clientSecret === '' ? '&client_id=' . rawurlencode($oidcProvider->clientId) : '');
        $response = $this->sent($this->request($oidcProvider, $body));
        $idToken = $this->stringMapJson->decode((string) $response->getBody())['id_token'] ?? '';

        if ($response->getStatusCode() !== 200 || $idToken === '') {
            throw new SignInRefusedException(
                'The sign-in provider would not exchange the code (status ' . $response->getStatusCode() . ').',
            );
        }

        return $idToken;
    }

    /**
     * @param OidcProvider $oidcProvider The provider.
     * @param string       $body         The form body.
     *
     * @throws InvalidArgumentException When a header or the copy is not a request.
     *
     * @return RequestInterface The token request, authenticated by HTTP Basic for a confidential client.
     */
    private function request(OidcProvider $oidcProvider, #[SensitiveParameter]
        string $body, ): RequestInterface
    {
        $message = $this->requestFactory->createRequest('POST', $oidcProvider->tokenEndpoint)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        if ($oidcProvider->clientSecret !== '') {
            $credentials = rawurlencode($oidcProvider->clientId) . ':' . rawurlencode($oidcProvider->clientSecret);
            $message = $message->withHeader('Authorization', 'Basic ' . base64_encode($credentials));
        }

        return self::asRequest($message);
    }

    /**
     * @param RequestInterface $request The token request.
     *
     * @throws SignInRefusedException When the provider cannot be reached.
     *
     * @return ResponseInterface Its answer, whatever the status.
     */
    private function sent(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $clientException) {
            throw new SignInRefusedException('The sign-in provider could not be reached.', 0, $clientException);
        }
    }

    /**
     * @param MessageInterface $message A request's copy, which a PSR-7 wither types as the message it is.
     *
     * @throws InvalidArgumentException When it is not a request.
     *
     * @return RequestInterface The copy, as a request.
     */
    private static function asRequest(MessageInterface $message): RequestInterface
    {
        if (!$message instanceof RequestInterface) {
            throw new InvalidArgumentException('A request wither must return a request.');
        }

        return $message;
    }
}
