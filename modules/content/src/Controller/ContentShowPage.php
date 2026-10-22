<?php

declare(strict_types=1);

namespace App\Content\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Content\Component\ContentArticle;
use App\Content\Entity\ContentPage;
use App\Content\Repository\ContentRepository;
use App\Content\Service\ContentRequest;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /pages/{slug}: the content page as an article (#50); 404 when there is
 * no such page.
 */
final readonly class ContentShowPage implements Page
{
    /**
     * @param HtmlLayout        $htmlLayout        Makes the page.
     * @param ContentRepository $contentRepository The pages.
     * @param ContentRequest    $contentRequest    Reads the slug.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private ContentRepository $contentRepository,
        private ContentRequest $contentRequest,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `slug` attribute.
     *
     * @throws QueryException When the page cannot be read.
     *
     * @return PageResponse The page, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $slug = $this->contentRequest->slug($serverRequest);
        $contentPage = $slug === ''
            ? null
            : $this->contentRepository->find($slug);

        if (!$contentPage instanceof ContentPage) {
            return $this->htmlLayout->content('<p id="not-found">There is no page here.</p>', 404);
        }

        return $this->htmlLayout->content((new ContentArticle($contentPage))->render()->markup(), 200);
    }
}
