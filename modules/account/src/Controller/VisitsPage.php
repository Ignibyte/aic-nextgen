<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Form\Application\FormTokens;
use AIC\Session\Application\RequestSession;
use App\Site\Service\HtmlLayout;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /visits: counts this page's visits in the visitor's session, with a
 * button that forgets the session.
 */
final readonly class VisitsPage implements Page
{
    /**
     * @param HtmlLayout     $htmlLayout     Makes the page.
     * @param RequestSession $requestSession Reads the session off the request.
     * @param FormTokens     $formTokens     Issues the visitor's CSRF token.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private RequestSession $requestSession,
        private FormTokens $formTokens,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The count.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $session = $this->requestSession->read($serverRequest);
        $visits = (int) ($session->get('visits') ?? '0') + 1;
        $session->set('visits', (string) $visits);
        $fields = [new HiddenInput('next', '/visits'), new Button('Forget me', 'submit', '')];
        $form = new Form('forget', '/visits/forget', $fields, $this->formTokens->issue($serverRequest)->value());
        $seen = 'This session has seen this page ' . $visits . ($visits === 1 ? ' time.' : ' times.');

        return $this->htmlLayout->content([new Paragraph($seen, 'visits'), $form], 200);
    }
}
