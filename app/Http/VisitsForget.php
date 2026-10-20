<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Session\Application\RequestSession;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /visits/forget: clears the visitor's session, which ends it, and
 * goes back to /visits.
 */
final readonly class VisitsForget implements Page
{
    /**
     * @param RequestSession $requestSession Reads the session off the request.
     */
    public function __construct(
        private RequestSession $requestSession,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @return PageResponse 303 to /visits.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->requestSession->read($serverRequest)->clear();

        return new PageRedirect('/visits');
    }
}
