<?php

declare(strict_types=1);

namespace App\Session\Controller;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Form\Application\FormTokens;
use AIC\Session\Application\RequestSession;
use App\Site\Service\HtmlLayout;
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
     * @return PageResponse The count.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $session = $this->requestSession->read($serverRequest);
        $visits = (int) ($session->get('visits') ?? '0') + 1;
        $session->set('visits', (string) $visits);
        $next = new Html('<input type="hidden" name="next" value="/visits">');
        $button = $next->append((new Button('Forget me', 'submit', ''))->render());
        $html = (new Form('forget', '/visits/forget', $button, $this->formTokens->issue($serverRequest)))->render();

        return $this->htmlLayout->content(
            '<p id="visits">This session has seen this page ' . $visits . ($visits === 1 ? ' time.' : ' times.') . '</p>'
            . $html->markup(),
            200,
        );
    }
}
