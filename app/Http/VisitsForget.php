<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Domain\SameOriginPath;
use AIC\Session\Application\RequestSession;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;

/**
 * POST /visits/forget: clears the visitor's session, which ends it, and
 * goes to the form's `next` when it is a path on this site (#43), else back
 * to /visits.
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
     * @return PageResponse 303 to `next`, or to /visits.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->requestSession->read($serverRequest)->clear();
        $body = $serverRequest->getParsedBody();
        $next = is_array($body)
            ? $body['next'] ?? ''
            : '';

        return new PageRedirect((new SameOriginPath('/visits'))->resolve(is_string($next) ? $next : ''));
    }
}
