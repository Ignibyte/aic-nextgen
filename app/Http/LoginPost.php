<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Auth\Application\Authenticator;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use AIC\Form\Domain\SameOriginPath;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /login: signs the user in and goes to `next` when it is a path on
 * this site, else /account (303); a wrong login or password shows the form
 * again (422), never saying which was wrong.
 */
final readonly class LoginPost implements Page
{
    /**
     * @param HtmlLayout    $htmlLayout    Makes the page.
     * @param LoginForm     $loginForm     The form, shown again on a failure.
     * @param Authenticator $authenticator Signs the user in.
     * @param PostedFields  $postedFields  Reads the posted fields.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private LoginForm $loginForm,
        private Authenticator $authenticator,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When the user cannot be read.
     *
     * @return PageResponse 303 to `next` or /account, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $login = $this->postedFields->value($serverRequest, 'login');
        $next = $this->postedFields->value($serverRequest, 'next');

        if (!$this->authenticator->login($serverRequest, $login, $this->postedFields->value($serverRequest, 'password'))) {
            $form = $this->loginForm->render($serverRequest, $login, $next, 'Wrong login or password.');

            return $this->htmlLayout->content('<h2>Sign in</h2>' . $form->markup(), 422);
        }

        return new PageRedirect((new SameOriginPath('/account'))->resolve($next));
    }
}
