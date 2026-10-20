<?php

declare(strict_types=1);

namespace App\Content;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Site\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /account/pages/{slug}: the editor for a content page (#50), empty for
 * a new one; 404 for a slug of the wrong shape.
 */
final readonly class ContentEditPage implements Page
{
    /**
     * @param HtmlLayout        $htmlLayout        Makes the page.
     * @param ContentRepository $contentRepository The pages.
     * @param ContentRequest    $contentRequest    Reads the slug.
     * @param ContentForm       $contentForm       The editor.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private ContentRepository $contentRepository,
        private ContentRequest $contentRequest,
        private ContentForm $contentForm,
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
            return $this->htmlLayout->content('<p id="not-found">There is no page here.</p>', 404);
        }

        $contentPage = $this->contentRepository->find($slug) ?? new ContentPage($slug, '', '');

        return $this->htmlLayout->content(
            '<h2>Edit /pages/' . $slug . '</h2>' . $this->contentForm->editor($serverRequest, $contentPage, '')->markup(),
            200,
        );
    }
}
