<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Access\Domain\Actor;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Domain\ClientRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\AuthorizationRequest;
use AIC\OAuth\Domain\OAuthClient;
use AIC\OAuth\Domain\TokenAudience;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function preg_match;

/**
 * The authorization endpoint's checks and answers (#331), for the module
 * page that shows the consent: a request is read from its fields (the query,
 * or the consent form's post) and checked; an unknown client or a redirect URI
 * the client did not register is refused on the page and never redirected
 * to, any other fault goes back to the client as its error. Allowing issues a
 * code for the scopes asked that the person's roles grant; denying sends
 * `access_denied`.
 */
final readonly class Authorizations
{
    /**
     * A PKCE S256 challenge: 43 base64url characters.
     */
    private const string CHALLENGE = '/^[A-Za-z0-9_-]{43}$/';

    /**
     * The fields an authorization request is read from, and the consent form's decision.
     */
    private const array FIELDS = [
        'client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'code_challenge', 'code_challenge_method',
        'resource', 'decision',
    ];

    /**
     * @param AppConfig          $appConfig          The app's settings, with the server's.
     * @param OAuthClients       $oauthClients       The registered clients.
     * @param AuthorizationCodes $authorizationCodes Issues the codes.
     * @param TokenScopes        $tokenScopes        Reads the scopes asked for.
     * @param RequestFields      $requestFields      Reads the request's fields.
     * @param TokenAudience      $tokenAudience      Reads the path a resource names.
     */
    public function __construct(
        private AppConfig $appConfig,
        private OAuthClients $oauthClients,
        private AuthorizationCodes $authorizationCodes,
        private TokenScopes $tokenScopes = new TokenScopes(),
        private RequestFields $requestFields = new RequestFields(),
        private TokenAudience $tokenAudience = new TokenAudience(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The authorization request, or the consent form's post.
     *
     * @throws QueryException        When the clients cannot be read.
     * @throws OAuthRefusedException When the server is off, the client is not known or the redirect URI is not one it
     *                               registered: the page says so and nothing is redirected.
     *
     * @return AuthorizationRequest The request, with its refusal when a later check failed.
     */
    public function read(ServerRequestInterface $serverRequest): AuthorizationRequest
    {
        $fields = $this->requestFields->read($serverRequest, self::FIELDS);
        $client = $this->client($fields['client_id'] ?? '', $fields['redirect_uri'] ?? '');
        $redirectUri = $fields['redirect_uri'] ?? '';
        $state = $fields['state'] ?? '';
        $challenge = $fields['code_challenge'] ?? '';
        $resource = $fields['resource'] ?? '';

        try {
            $scopes = $this->tokenScopes->asked($client, $fields['scope'] ?? '');
        } catch (OAuthRefusedException $oAuthRefusedException) {
            return new AuthorizationRequest($client->id, $redirectUri, [], $state, $challenge, $resource, $oAuthRefusedException->error);
        }

        $bindable = $this->tokenAudience->path($resource, $this->appConfig->oauth) !== null;
        $refusal = self::refusal($fields, $challenge, $bindable);

        return new AuthorizationRequest($client->id, $redirectUri, $scopes, $state, $challenge, $resource, $refusal);
    }

    /**
     * @param ServerRequestInterface $serverRequest The consent form's post.
     *
     * @return bool Whether the person pressed allow.
     */
    public function allowed(ServerRequestInterface $serverRequest): bool
    {
        return ($this->requestFields->read($serverRequest, ['decision'])['decision'] ?? '') === 'allow';
    }

    /**
     * @param AuthorizationRequest $authorizationRequest A sound request.
     * @param Actor                $actor                The signed-in person allowing it.
     *
     * @return list<string> The scopes asked for that the person's roles grant.
     */
    public function grantable(AuthorizationRequest $authorizationRequest, Actor $actor): array
    {
        $scopes = [];

        foreach ($authorizationRequest->scopes as $scope) {
            if (!in_array($scope, $actor->permissions, true)) {
                continue;
            }

            $scopes[] = $scope;
        }

        return $scopes;
    }

    /**
     * @param AuthorizationRequest $authorizationRequest A sound request.
     * @param Actor                $actor                The signed-in person allowing it.
     *
     * @throws QueryException When the code cannot be written.
     *
     * @return ClientRedirect Back to the client with a code, or with `invalid_scope` when the person can grant none.
     */
    public function allow(AuthorizationRequest $authorizationRequest, Actor $actor): ClientRedirect
    {
        $scopes = $this->grantable($authorizationRequest, $actor);

        if ($scopes === [] || !$actor->signedIn()) {
            return $this->refuse($authorizationRequest, 'invalid_scope');
        }

        $code = $this->authorizationCodes->issue($authorizationRequest, $actor->userId, $scopes, $this->appConfig->oauth);

        return new ClientRedirect($authorizationRequest, ['code' => $code]);
    }

    /**
     * @param AuthorizationRequest $authorizationRequest The request.
     * @param string               $error                The error: its refusal, or `access_denied`.
     *
     * @return ClientRedirect Back to the client with the error.
     */
    public function refuse(AuthorizationRequest $authorizationRequest, string $error): ClientRedirect
    {
        return new ClientRedirect($authorizationRequest, ['error' => $error]);
    }

    /**
     * @param string $clientId    The client the request names.
     * @param string $redirectUri The redirect URI it names.
     *
     * @throws QueryException        When the clients cannot be read.
     * @throws OAuthRefusedException When the server is off, the client is not known or the redirect URI is not one it
     *                               registered.
     *
     * @return OAuthClient The client.
     */
    private function client(string $clientId, string $redirectUri): OAuthClient
    {
        $client = $this->appConfig->oauth->enabled()
            ? $this->oauthClients->client($clientId)
            : null;

        if (!$client instanceof OAuthClient || !$client->returnsTo($redirectUri)) {
            throw new OAuthRefusedException(
                'invalid_request',
                'The client is not known here, or that is not a redirect URI it registered.',
            );
        }

        return $client;
    }

    /**
     * @param array<string, string> $fields    The request's fields.
     * @param string                $challenge Its PKCE challenge.
     * @param bool                  $bindable  Whether the resource it names, if any, is a path of this site.
     *
     * @return string The first fault's error; empty for none.
     */
    private static function refusal(array $fields, string $challenge, bool $bindable): string
    {
        if (($fields['response_type'] ?? '') !== 'code') {
            return 'unsupported_response_type';
        }

        if (($fields['code_challenge_method'] ?? '') !== 'S256' || preg_match(self::CHALLENGE, $challenge) !== 1) {
            return 'invalid_request';
        }

        return $bindable
            ? ''
            : 'invalid_target';
    }
}
