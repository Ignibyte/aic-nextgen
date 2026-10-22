<?php

declare(strict_types=1);

namespace App\Content\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use App\Content\Entity\ContentPage;
use App\Content\Repository\ContentRepository;
use App\Content\Service\ContentRequest;
use App\Content\Service\ContentViews;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function trim;

/**
 * POST /account/pages/{slug}: saves a content page (#50) and goes to it
 * (303); a blank title shows the editor again (422).
 */
final readonly class ContentSavePost implements Page
{
    /**
     * @param ContentRepository $contentRepository The pages.
     * @param ContentRequest    $contentRequest    Reads the slug.
     * @param ContentViews      $contentViews      The editor and the 404.
     * @param PostedFields      $postedFields      Reads the title and the text.
     */
    public function __construct(
        private ContentRepository $contentRepository,
        private ContentRequest $contentRequest,
        private ContentViews $contentViews,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted editor, with the `slug` attribute and the session.
     *
     * @throws QueryException When the page cannot be stored.
     *
     * @return PageResponse 303 to the page, the editor again as 422, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $slug = $this->contentRequest->slug($serverRequest);

        if ($slug === '') {
            return $this->contentViews->notFound();
        }

        $title = trim($this->postedFields->value($serverRequest, 'title'));
        $contentPage = new ContentPage($slug, $title, $this->postedFields->value($serverRequest, 'body'));

        if ($title === '') {
            return $this->contentViews->editor($serverRequest, $contentPage, 'A page needs a title.', 422);
        }

        $this->contentRepository->save($contentPage);

        return new PageRedirect('/pages/' . $slug);
    }
}
