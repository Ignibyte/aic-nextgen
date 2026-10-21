<?php

declare(strict_types=1);

namespace App\Auth;

use AIC\Auth\Application\Authenticator;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageFullVisit;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /logout: signs the user out, ending the session, and goes home.
 */
final readonly class LogoutPost implements Page
{
    /**
     * @param Authenticator $authenticator Signs the user out.
     */
    public function __construct(
        private Authenticator $authenticator,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @return PageResponse A full visit to /: a 303, or `Tessera-Full-Visit` for Tessera (#71), so nothing of the
     *                      session survives in the page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->authenticator->logout($serverRequest);

        return new PageFullVisit('/');
    }
}
