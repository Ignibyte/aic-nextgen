<?php

declare(strict_types=1);

namespace App\Content\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Content\Entity\ContentPage;
use App\Content\Repository\ContentRepository;
use App\Content\Service\ContentRequest;
use App\Content\Service\ContentViews;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /account/pages/{slug}: the editor for a content page (#50), empty for
 * a new one; 404 for a slug of the wrong shape.
 */
final readonly class ContentEditPage implements Page
{
    /**
     * @param ContentRepository $contentRepository The pages.
     * @param ContentRequest    $contentRequest    Reads the slug.
     * @param ContentViews      $contentViews      The editor and the 404.
     */
    public function __construct(
        private ContentRepository $contentRepository,
        private ContentRequest $contentRequest,
        private ContentViews $contentViews,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `slug` attribute and the session.
     *
     * @throws QueryException When the page cannot be read.
     *
     * @return PageResponse The editor, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $slug = $this->contentRequest->slug($serverRequest);

        if ($slug === '') {
            return $this->contentViews->notFound();
        }

        $contentPage = $this->contentRepository->find($slug) ?? new ContentPage($slug, '', '');

        return $this->contentViews->editor($serverRequest, $contentPage, '', 200);
    }
}
