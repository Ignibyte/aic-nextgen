<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Auth\Application\Authenticator;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
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
     * @return PageResponse 303 to /.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->authenticator->logout($serverRequest);

        return new PageRedirect('/');
    }
}
