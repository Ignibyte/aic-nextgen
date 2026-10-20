<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Auth\Application\Authenticator;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Form\Application\FormTokens;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function htmlspecialchars;

/**
 * GET /account: who is signed in, and the sign-out button. The route is
 * protected (RouteAuthConfig), so an anonymous visitor never reaches it.
 */
final readonly class AccountPage implements Page
{
    /**
     * @param HtmlLayout    $htmlLayout    Makes the page.
     * @param Authenticator $authenticator Knows who is signed in.
     * @param FormTokens    $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private Authenticator $authenticator,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in user.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $user = $this->authenticator->userId($serverRequest);
        $html = (new Button('Sign out', 'submit', 'sign-out'))->render();
        $signOut = (new Form('', '/logout', $html, $this->formTokens->issue($serverRequest)))->render();

        return $this->htmlLayout->content(
            '<h2>Account</h2><p id="signed-in">Signed in as ' . htmlspecialchars($user) . '.</p>' . $signOut->markup(),
            200,
        );
    }
}
