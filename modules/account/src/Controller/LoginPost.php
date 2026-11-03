<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Auth\Application\Authenticator;
use AIC\Auth\Domain\SignedInUser;
use AIC\Component\Domain\Heading;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use AIC\Form\Domain\SameOriginPath;
use AIC\Session\Application\RequestSession;
use App\Account\Form\LoginForm;
use LogicException;
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
     * The session key that keeps the day the user joined, read at sign-in for the account page (#238).
     */
    public const string JOINED = 'account.joined';

    /**
     * @param PageDocuments  $pageDocuments  Makes the page.
     * @param LoginForm      $loginForm      The form, shown again on a failure.
     * @param Authenticator  $authenticator  Signs the user in.
     * @param PostedFields   $postedFields   Reads the posted fields.
     * @param RequestSession $requestSession Keeps the day the user joined, from the sign-in (#238).
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private LoginForm $loginForm,
        private Authenticator $authenticator,
        private PostedFields $postedFields = new PostedFields(),
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When the user cannot be read.
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse 303 to `next` or /account, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $login = $this->postedFields->value($serverRequest, 'login');
        $next = $this->postedFields->value($serverRequest, 'next');

        $signedIn = $this->authenticator->login($serverRequest, $login, $this->postedFields->value($serverRequest, 'password'));

        if (!$signedIn instanceof SignedInUser) {
            $form = $this->loginForm->form($serverRequest, $login, $next, 'Wrong login or password.');

            return $this->pageDocuments->content([new Heading('Sign in'), $form], 422, 'Sign in');
        }

        $this->requestSession->read($serverRequest)->set(self::JOINED, $signedIn->profile['joined'] ?? '');

        return new PageRedirect((new SameOriginPath('/account'))->resolve($next));
    }
}
