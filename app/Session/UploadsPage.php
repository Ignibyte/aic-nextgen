<?php

declare(strict_types=1);

namespace App\Session;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Session\Application\RequestSession;
use App\Site\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function htmlspecialchars;

/**
 * GET /uploads: the upload form, and what the last accepted upload was, kept
 * in the session by UploadsPost.
 */
final readonly class UploadsPage implements Page
{
    /**
     * The session key that keeps the last accepted upload.
     */
    public const string LAST = 'uploads.last';

    /**
     * @param HtmlLayout     $htmlLayout     Makes the page.
     * @param RequestSession $requestSession Reads the session off the request.
     * @param UploadForm     $uploadForm     The form.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private RequestSession $requestSession,
        private UploadForm $uploadForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $last = $this->requestSession->read($serverRequest)->get(self::LAST) ?? '';
        $summary = $last === ''
            ? 'Nothing uploaded yet.'
            : 'Accepted ' . $last . '.';

        return $this->htmlLayout->content(
            '<p id="upload-last">' . htmlspecialchars($summary) . '</p>' . $this->uploadForm->render($serverRequest, '')->markup(),
            200,
        );
    }
}
