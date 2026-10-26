<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Account\Form\LoginForm;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;

/**
 * GET /login: the sign-in form, carrying the `next` it was sent with (#9).
 */
final readonly class LoginPage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     * @param LoginForm     $loginForm     The form.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private LoginForm $loginForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session and perhaps `?next=`.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $query = $serverRequest->getQueryParams();
        $next = array_key_exists('next', $query) && is_string($query['next'])
            ? $query['next']
            : '';

        return $this->pageDocuments->content(
            [new Heading('Sign in'), $this->loginForm->form($serverRequest, '', $next, '')],
            200,
        );
    }
}
