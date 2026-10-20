<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Application\PageHandlers;
use AIC\Form\Application\FormTokens;
use AIC\Form\Application\UploadCheck;
use AIC\Form\Domain\UploadKind;
use AIC\Session\Application\RequestSession;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The pages that keep state in the session, the visits and the uploads, each
 * wrapped in the Page handler, by the handler id config/routes.php names.
 */
final readonly class VisitHandlers
{
    /**
     * The largest upload the demo accepts, 1 MiB.
     */
    private const int UPLOAD_LIMIT = 1_048_576;

    /**
     * The kinds of file the demo accepts.
     */
    private const array UPLOAD_KINDS = [UploadKind::Png, UploadKind::Jpeg, UploadKind::Gif, UploadKind::Pdf, UploadKind::Text];

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
        $uploadForm = new UploadForm($formTokens);

        return [
            UploadsPage::class => $this->pageHandlers->handler(new UploadsPage($this->htmlLayout, $requestSession, $uploadForm)),
            UploadsPost::class => $this->pageHandlers->handler(
                new UploadsPost($this->htmlLayout, $requestSession, $uploadForm, new UploadCheck(self::UPLOAD_LIMIT, self::UPLOAD_KINDS)),
            ),
            VisitsForget::class => $this->pageHandlers->handler(new VisitsForget($requestSession)),
            VisitsPage::class => $this->pageHandlers->handler(new VisitsPage($this->htmlLayout, $requestSession, $formTokens)),
        ];
    }
}
