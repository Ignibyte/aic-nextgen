<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Access\Application\BearerTokens;
use AIC\Database\Contracts\QueryException;
use AIC\Http\Domain\BearerCredential;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\ClientRegistration;
use AIC\OAuth\Domain\OAuthServer;
use AIC\OAuth\Domain\RegistrationRequest;
use AIC\Session\Domain\RandomTokens;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function implode;
use function in_array;
use function substr;
use function time;

/**
 * `POST /oauth/register` (RFC 7591, #352), mounted only while
 * `OAuthServer::$registration` is not `Off`: a client registers itself from
 * a JSON body (`RegistrationReader`). Under `Scoped` the caller presents a
 * bearer token holding `oauth.register` (RFC 7591's initial access token,
 * an operator's `token:add`); under `Open` anyone may. The client gets a
 * generated id (`dcr-` and 16 hex characters) and, unless it asked for
 * `none`, a secret shown once; its scopes stay within `registrationScopes`.
 * The answer is 201 with what was registered (section 3.2.1).
 */
final readonly class ClientRegistrar
{
    /**
     * The permission a `Scoped` registration's bearer token needs.
     */
    public const string PERMISSION = 'oauth.register';

    /**
     * @param OAuthServer        $oauthServer        The server's settings.
     * @param OAuthClients       $oauthClients       Keeps the client.
     * @param BearerTokens       $bearerTokens       Reads an initial access token.
     * @param OAuthAnswers       $oauthAnswers       Makes the answer.
     * @param RandomTokens       $randomTokens       Makes the id and the secret.
     * @param BearerCredential   $bearerCredential   Reads the Authorization header.
     * @param RegistrationReader $registrationReader Reads the request.
     */
    public function __construct(
        private OAuthServer $oauthServer,
        private OAuthClients $oauthClients,
        private BearerTokens $bearerTokens,
        private OAuthAnswers $oauthAnswers,
        private RandomTokens $randomTokens = new RandomTokens(),
        private BearerCredential $bearerCredential = new BearerCredential(),
        private RegistrationReader $registrationReader = new RegistrationReader(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to the registration endpoint.
     *
     * @throws QueryException           When the tokens or clients cannot be read or written.
     * @throws InvalidArgumentException When a response cannot be made.
     *
     * @return ResponseInterface The registered client, or the refusal.
     */
    public function answer(ServerRequestInterface $serverRequest): ResponseInterface
    {
        if ($serverRequest->getMethod() !== 'POST') {
            return $this->oauthAnswers->postOnly('registration endpoint');
        }

        try {
            $this->admitted($serverRequest);
            $registrationRequest = $this->registrationReader->read(
                (string) $serverRequest->getBody(),
                $this->oauthServer->registrationScopes,
            );

            return $this->registered($registrationRequest);
        } catch (OAuthRefusedException $oAuthRefusedException) {
            return $this->oauthAnswers->refused($oAuthRefusedException);
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws OAuthRefusedException When registration is not open and the bearer token does not hold the permission.
     * @throws QueryException        When the tokens cannot be read.
     */
    private function admitted(ServerRequestInterface $serverRequest): void
    {
        if ($this->oauthServer->registration === ClientRegistration::Open) {
            return;
        }

        $actor = $this->bearerCredential->present($serverRequest)
            ? $this->bearerTokens->actor($this->bearerCredential->token($serverRequest), $serverRequest->getUri()->getPath())
            : null;

        if ($actor === null || !in_array(self::PERMISSION, $actor->permissions, true)) {
            throw new OAuthRefusedException('invalid_token', 'Registration needs a bearer token holding oauth.register.', 401);
        }
    }

    /**
     * @param RegistrationRequest $registrationRequest The checked request.
     *
     * @throws QueryException           When the client cannot be written.
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface The 201 with the registered metadata.
     */
    private function registered(RegistrationRequest $registrationRequest): ResponseInterface
    {
        $clientId = 'dcr-' . substr($this->randomTokens->fresh(), 0, 16);
        $secret = $registrationRequest->confidential()
            ? $this->randomTokens->fresh()
            : '';
        $this->oauthClients->add($clientId, $secret, $registrationRequest->scopes, $registrationRequest->redirectUris);
        $secretMembers = $secret === ''
            ? ''
            : ',"client_secret":' . $this->oauthAnswers->text($secret) . ',"client_secret_expires_at":0';

        return $this->oauthAnswers->document(
            201,
            '{"client_id":' . $this->oauthAnswers->text($clientId) . $secretMembers . ',"client_id_issued_at":' . time()
                . ',"redirect_uris":' . $this->oauthAnswers->texts($registrationRequest->redirectUris)
                . ',"token_endpoint_auth_method":' . $this->oauthAnswers->text($registrationRequest->authMethod)
                . ',"grant_types":' . $this->oauthAnswers->texts($registrationRequest->grantTypes)
                . ',"response_types":["code"],"scope":' . $this->oauthAnswers->text(implode(' ', $registrationRequest->scopes)) . '}',
        );
    }
}
