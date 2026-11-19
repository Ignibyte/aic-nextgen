<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Auth\Application\Authenticator;
use AIC\Auth\Application\OidcSignIn;
use AIC\Auth\Contracts\SignInRefusedException;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Session\Application\RequestSession;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `GET /sign-in/oidc/callback` (#313): where the provider sends the browser
 * back. A good sign-in signs the person in as `<provider>:<subject>`, keeps
 * the provider's email for the account page and goes to `/account`; any
 * other goes back to `/login`, which says why.
 */
final readonly class OidcCallbackPage implements Page
{
    /**
     * The session key the login page reads a refused sign-in's reason from.
     */
    public const string REFUSED = 'account.sign-in-refused';

    /**
     * The session key the account page reads the provider's email from.
     */
    public const string EMAIL = 'account.email';

    /**
     * @param OidcSignIn     $oidcSignIn     The sign-in.
     * @param Authenticator  $authenticator  Signs the identity in.
     * @param RequestSession $requestSession The request's session, for a refusal.
     */
    public function __construct(
        private OidcSignIn $oidcSignIn,
        private Authenticator $authenticator,
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The callback, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return PageResponse 303 to `/account`, or to `/login`.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        try {
            $oidcIdentity = $this->oidcSignIn->finish($serverRequest);
        } catch (SignInRefusedException $signInRefusedException) {
            $this->requestSession->read($serverRequest)->set(self::REFUSED, $signInRefusedException->getMessage());

            return new PageRedirect('/login');
        }

        $this->authenticator->signInAs($serverRequest, $oidcIdentity->provider . ':' . $oidcIdentity->subject);
        $session = $this->requestSession->read($serverRequest);
        $session->set(self::EMAIL, $oidcIdentity->email);
        $session->set(LoginPost::JOINED, '');

        return new PageRedirect('/account');
    }
}
