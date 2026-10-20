<?php

declare(strict_types=1);

namespace App\Auth;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * GET /login: the sign-in form, carrying the `next` it was sent with (#9).
 */
final readonly class LoginPage implements Page
{
    /**
     * @param HtmlLayout $htmlLayout Makes the page.
     * @param LoginForm  $loginForm  The form.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private LoginForm $loginForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session and perhaps `?next=`.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $query = $serverRequest->getQueryParams();
        $next = $query['next'] ?? '';

        return $this->htmlLayout->content(
            '<h2>Sign in</h2>' . $this->loginForm->render($serverRequest, '', is_string($next) ? $next : '', '')->markup(),
            200,
        );
    }
}
