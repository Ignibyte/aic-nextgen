<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Application\PageHandlers;
use AIC\Form\Application\FormTokens;
use AIC\Session\Application\RequestSession;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The visits pages, which keep their count in the session, each wrapped in
 * the Page handler, by the handler id config/routes.php names.
 */
final readonly class VisitHandlers
{
    /**
     * @param HtmlLayout   $htmlLayout   Makes the pages.
     * @param PageHandlers $pageHandlers Wraps each page with the response writer and the blocks.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PageHandlers $pageHandlers,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $requestSession = new RequestSession();
        $formTokens = new FormTokens($requestSession);

        return [
            VisitsForget::class => $this->pageHandlers->handler(new VisitsForget($requestSession)),
            VisitsPage::class => $this->pageHandlers->handler(new VisitsPage($this->htmlLayout, $requestSession, $formTokens)),
        ];
    }
}
