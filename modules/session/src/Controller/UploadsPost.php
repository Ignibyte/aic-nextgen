<?php

declare(strict_types=1);

namespace App\Session\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageDocument;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Application\UploadCheck;
use AIC\Form\Exception\RefusedUpload;
use AIC\Session\Application\RequestSession;
use App\Session\Form\UploadForm;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * POST /uploads: checks the uploaded file (#44). An accepted one is noted in
 * the session and the page shown again (303); a refused one shows the form
 * with the reason (422). The demo keeps no file.
 */
final readonly class UploadsPost implements Page
{
    /**
     * @param HtmlLayout     $htmlLayout     Makes the page.
     * @param RequestSession $requestSession Reads the session off the request.
     * @param UploadForm     $uploadForm     The form, shown again on a refusal.
     * @param UploadCheck    $uploadCheck    Checks the file.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private RequestSession $requestSession,
        private UploadForm $uploadForm,
        private UploadCheck $uploadCheck,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
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
     * @return PageDocument The form again, with the reason, as 422.
     */
    private function refused(ServerRequestInterface $serverRequest, string $reason): PageDocument
    {
        return $this->htmlLayout->content([$this->uploadForm->form($serverRequest, $reason)], 422);
    }
}
