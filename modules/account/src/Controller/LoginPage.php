<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Account\Form\LoginForm;
use App\Site\Service\HtmlLayout;
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
            [new Heading('Sign in'), $this->loginForm->form($serverRequest, '', is_string($next) ? $next : '', '')],
            200,
        );
    }
}
