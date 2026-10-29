<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Component\Domain\Link;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Session\Application\RequestSession;
use App\Account\Form\UploadForm;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

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
     * The session key for the last accepted upload's public URL path (#213).
     */
    public const string KEPT = 'uploads.kept';

    /**
     * @param PageDocuments  $pageDocuments  Makes the page.
     * @param RequestSession $requestSession Reads the session off the request.
     * @param UploadForm     $uploadForm     The form.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private RequestSession $requestSession,
        private UploadForm $uploadForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $session = $this->requestSession->read($serverRequest);
        $last = $session->get(self::LAST) ?? '';
        $kept = $session->get(self::KEPT) ?? '';
        $summary = $last === ''
            ? 'Nothing uploaded yet.'
            : 'Accepted ' . $last . '.';
        $components = [new Paragraph($summary, 'upload-last')];

        if ($kept !== '') {
            $components[] = new Link('The public copy', $kept);
            $components[] = new Link('The private copy', '/uploads/latest');
        }

        $components[] = $this->uploadForm->form($serverRequest, '');

        return $this->pageDocuments->content($components, 200, 'Uploads');
    }
}
