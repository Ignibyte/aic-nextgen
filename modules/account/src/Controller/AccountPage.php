<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Auth\Application\Authenticator;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Form\Application\FormTokens;
use App\Site\Service\HtmlLayout;
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
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $user = $this->authenticator->userId($serverRequest);
        $form = new Form('', '/logout', [new Button('Sign out', 'submit', 'sign-out')], $this->formTokens->issue($serverRequest));

        return $this->htmlLayout->content(
            [new Heading('Account'), new Paragraph('Signed in as ' . $user . '.', 'signed-in'), $form],
            200,
        );
    }
}
