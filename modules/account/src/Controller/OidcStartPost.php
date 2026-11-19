<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Auth\Application\OidcSignIn;
use AIC\Auth\Contracts\SignInRefusedException;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Session\Application\RequestSession;
use App\Account\Service\SignInProviders;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `POST /sign-in/oidc` (#313): the login page's provider button. A post, so
 * its CSRF token keeps another site from starting a sign-in in a visitor's
 * session; it answers 303 to the provider, or back to `/login` with why not.
 */
final readonly class OidcStartPost implements Page
{
    /**
     * @param OidcSignIn     $oidcSignIn     The sign-in.
     * @param RequestSession $requestSession The request's session, for a refusal.
     */
    public function __construct(
        private OidcSignIn $oidcSignIn,
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The post, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return PageResponse 303 to the provider, or to `/login`.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        try {
            return $this->oidcSignIn->start($serverRequest, SignInProviders::PROVIDER);
        } catch (SignInRefusedException $signInRefusedException) {
            $this->requestSession->read($serverRequest)->set(OidcCallbackPage::REFUSED, $signInRefusedException->getMessage());

            return new PageRedirect('/login');
        }
    }
}
