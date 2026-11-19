<?php

declare(strict_types=1);

namespace AIC\Auth\Application;

use AIC\Auth\Contracts\SignInRefusedException;
use AIC\Auth\Domain\IdTokenClaims;
use AIC\Auth\Domain\OidcIdentity;
use AIC\Auth\Domain\OidcProviders;
use AIC\Controller\Domain\ProviderRedirect;
use AIC\Session\Application\RequestSession;
use AIC\Session\Domain\RandomTokens;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function base64_encode;
use function hash;
use function hash_equals;
use function is_string;
use function rawurlencode;
use function rtrim;
use function strtr;
use function time;

/**
 * Sign-in through an OpenID Connect provider (#313), by the authorization
 * code flow with PKCE (S256). `start()` keeps a fresh state, nonce and
 * verifier in the session and sends the browser to the provider; `finish()`,
 * on the callback, checks the state, exchanges the code and checks the id
 * token's claims. Each sign-in is used once: finishing clears what start
 * kept, whatever the outcome. Who the identity is in the app, and signing
 * them in, is the caller's (`Authenticator::signInAs()`).
 */
final readonly class OidcSignIn
{
    /**
     * The session keys a sign-in keeps between its two requests.
     */
    private const array KEYS = [
        'nonce' => 'aic.oidc.nonce',
        'provider' => 'aic.oidc.provider',
        'redirect' => 'aic.oidc.redirect',
        'state' => 'aic.oidc.state',
        'verifier' => 'aic.oidc.verifier',
    ];

    /**
     * @param OidcProviders  $oidcProviders  The providers `config/app.php` names.
     * @param CodeExchange   $codeExchange   The token endpoint call.
     * @param RandomTokens   $randomTokens   Makes the state, the nonce and the verifier.
     * @param RequestSession $requestSession The request's session.
     */
    public function __construct(
        private OidcProviders $oidcProviders,
        private CodeExchange $codeExchange,
        private RandomTokens $randomTokens = new RandomTokens(),
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request that starts it, with its session.
     * @param string                 $name          The provider's name.
     *
     * @throws SignInRefusedException When the app names no such provider.
     * @throws LogicException         When the request carries no session.
     *
     * @return ProviderRedirect 303 to the provider's authorization endpoint.
     */
    public function start(ServerRequestInterface $serverRequest, string $name): ProviderRedirect
    {
        $oidcProvider = $this->oidcProviders->provider($name);
        $session = $this->requestSession->read($serverRequest);
        $redirectUri = $oidcProvider->redirectUri($serverRequest->getUri());
        $state = $this->randomTokens->fresh();
        $nonce = $this->randomTokens->fresh();
        $verifier = $this->randomTokens->fresh();
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $kept = ['nonce' => $nonce, 'provider' => $name, 'redirect' => $redirectUri, 'state' => $state, 'verifier' => $verifier];

        foreach ($kept as $part => $value) {
            $session->set(self::KEYS[$part], $value);
        }

        return new ProviderRedirect($oidcProvider, 'response_type=code&client_id=' . rawurlencode($oidcProvider->clientId)
            . '&redirect_uri=' . rawurlencode($redirectUri) . '&scope=' . rawurlencode($oidcProvider->scopes)
            . '&state=' . $state . '&nonce=' . $nonce . '&code_challenge=' . $challenge . '&code_challenge_method=S256');
    }

    /**
     * @param ServerRequestInterface $serverRequest The callback, with its session.
     *
     * @throws SignInRefusedException When the state does not match, the provider refused, or the id token fails a check.
     * @throws LogicException         When the request carries no session.
     *
     * @return OidcIdentity Who the provider signed in.
     */
    public function finish(ServerRequestInterface $serverRequest): OidcIdentity
    {
        $session = $this->requestSession->read($serverRequest);
        $kept = [];

        foreach (self::KEYS as $part => $key) {
            $kept[$part] = $session->get($key) ?? '';
            $session->set($key, '');
        }

        $state = self::query($serverRequest, 'state');

        if ($kept['state'] === '' || !hash_equals($kept['state'], $state)) {
            throw new SignInRefusedException('This sign-in was not started here, or has finished already.');
        }

        $error = self::query($serverRequest, 'error');

        if ($error !== '') {
            throw new SignInRefusedException('The sign-in provider refused: ' . $error . '.');
        }

        $oidcProvider = $this->oidcProviders->provider($kept['provider']);
        $idToken = $this->codeExchange->idToken($oidcProvider, self::query($serverRequest, 'code'), $kept['redirect'], $kept['verifier']);

        return (new IdTokenClaims($idToken))->identity($oidcProvider, $kept['provider'], $kept['nonce'], time());
    }

    /**
     * @param ServerRequestInterface $serverRequest A request.
     * @param string                 $name          A query parameter.
     *
     * @return string Its value when it is a string; empty otherwise.
     */
    private static function query(ServerRequestInterface $serverRequest, string $name): string
    {
        $query = $serverRequest->getQueryParams();

        return array_key_exists($name, $query) && is_string($query[$name])
            ? $query[$name]
            : '';
    }
}
