<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Auth\Application\Authenticator;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Form\Application\FormTokens;
use AIC\Session\Application\RequestSession;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /account: who is signed in, and the sign-out button. The route is
 * protected (RouteAuthConfig), so an anonymous visitor never reaches it.
 */
final readonly class AccountPage implements Page
{
    /**
     * @param PageDocuments  $pageDocuments  Makes the page.
     * @param Authenticator  $authenticator  Knows who is signed in.
     * @param FormTokens     $formTokens     Issues the visitor's CSRF token.
     * @param RequestSession $requestSession Reads the day the user joined, kept at sign-in (#238).
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private Authenticator $authenticator,
        private FormTokens $formTokens = new FormTokens(),
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in user.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $user = $this->authenticator->userId($serverRequest);
        $joined = $this->requestSession->read($serverRequest)->get(LoginPost::JOINED) ?? '';
        $signedIn = 'Signed in as ' . $user . ($joined === '' ? '.' : ', a member since ' . $joined . '.');
        $form = new Form('', '/logout', [new Button('Sign out', 'submit', 'sign-out')], $this->formTokens->issue($serverRequest)->value());

        return $this->pageDocuments->content(
            [new Heading('Account'), new Paragraph($signedIn, 'signed-in'), $form],
            200,
            'Account',
        );
    }
}
