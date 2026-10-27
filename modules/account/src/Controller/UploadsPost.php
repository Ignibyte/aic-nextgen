<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageDocument;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Application\UploadCheck;
use AIC\Form\Exception\RefusedUpload;
use AIC\Session\Application\RequestSession;
use App\Account\Form\UploadForm;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * POST /uploads: checks the uploaded file (#44). An accepted one is noted in
 * the session and the page shown again (303); a refused one shows the form
 * with the reason (422). The demo keeps no file.
 */
final readonly class UploadsPost implements Page
{
    /**
     * @param PageDocuments  $pageDocuments  Makes the page.
     * @param RequestSession $requestSession Reads the session off the request.
     * @param UploadForm     $uploadForm     The form, shown again on a refusal.
     * @param UploadCheck    $uploadCheck    Checks the file.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private RequestSession $requestSession,
        private UploadForm $uploadForm,
        private UploadCheck $uploadCheck,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws LogicException   When the required application state is unavailable.
     * @throws RuntimeException When the underlying operation fails.
     *
     * @return PageResponse 303 to /uploads, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $uploadedFiles = $serverRequest->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (!$file instanceof UploadedFileInterface) {
            return $this->refused($serverRequest, 'Choose a file to upload.');
        }

        return $this->accepted($serverRequest, $file);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form.
     * @param UploadedFileInterface  $uploadedFile  Its file.
     *
     * @throws LogicException   When the required application state is unavailable.
     * @throws RuntimeException When the underlying operation fails.
     *
     * @return PageResponse 303 when the check accepts it, else the form again as 422.
     */
    private function accepted(ServerRequestInterface $serverRequest, UploadedFileInterface $uploadedFile): PageResponse
    {
        try {
            $kind = $this->uploadCheck->check($uploadedFile);
        } catch (RefusedUpload $refusedUpload) {
            return $this->refused($serverRequest, $refusedUpload->getMessage());
        }

        $name = $uploadedFile->getClientFilename() ?? 'a file';
        $this->requestSession->read($serverRequest)
            ->set(UploadsPage::LAST, $name . ': ' . $kind . ', ' . ($uploadedFile->getSize() ?? 0) . ' bytes');

        return new PageRedirect('/uploads');
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form.
     * @param string                 $reason        Why the file was refused.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageDocument The form again, with the reason, as 422.
     */
    private function refused(ServerRequestInterface $serverRequest, string $reason): PageDocument
    {
        return $this->pageDocuments->content([$this->uploadForm->form($serverRequest, $reason)], 422, 'Uploads');
    }
}
