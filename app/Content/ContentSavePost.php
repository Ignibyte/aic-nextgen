<?php

declare(strict_types=1);

namespace App\Content;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use App\Site\HtmlLayout;
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
     * @param HtmlLayout        $htmlLayout        Makes the page.
     * @param ContentRepository $contentRepository The pages.
     * @param ContentRequest    $contentRequest    Reads the slug.
     * @param ContentForm       $contentForm       The editor, shown again on a refusal.
     * @param PostedFields      $postedFields      Reads the title and the text.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private ContentRepository $contentRepository,
        private ContentRequest $contentRequest,
        private ContentForm $contentForm,
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
            return $this->htmlLayout->content('<p id="not-found">There is no page here.</p>', 404);
        }

        $title = trim($this->postedFields->value($serverRequest, 'title'));
        $contentPage = new ContentPage($slug, $title, $this->postedFields->value($serverRequest, 'body'));

        if ($title === '') {
            $editor = $this->contentForm->editor($serverRequest, $contentPage, 'A page needs a title.');

            return $this->htmlLayout->content('<h2>Edit /pages/' . $slug . '</h2>' . $editor->markup(), 422);
        }

        $this->contentRepository->save($contentPage);

        return new PageRedirect('/pages/' . $slug);
    }
}
