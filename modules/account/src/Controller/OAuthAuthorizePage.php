<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Account\Service\Consent;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `GET` and `POST /oauth/authorize` (#331), the authorization endpoint for a
 * signed-in person: the consent page and its decision, or the client's error,
 * or the refusal.
 */
final readonly class OAuthAuthorizePage implements Page
{
    /**
     * @param Consent $consent Answers the authorization endpoint.
     */
    public function __construct(
        private Consent $consent,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The authorization request, or the decision past the CSRF check.
     *
     * @throws QueryException When the clients, the roles or the code cannot be read or written.
     * @throws LogicException When the request carries no session.
     *
     * @return PageResponse The consent page, 303 back to the client, or the refusal.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->consent->answer($serverRequest);
    }
}
