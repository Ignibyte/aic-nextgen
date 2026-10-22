<?php

declare(strict_types=1);

namespace App\Content\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Application\PostedFields;
use App\Content\Form\ContentForm;
use App\Content\Service\ContentRequest;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /account/pages: starts a content page by its slug (#50), going to its
 * editor (303); a slug of the wrong shape shows the form again (422).
 */
final readonly class ContentNewPost implements Page
{
    /**
     * @param HtmlLayout     $htmlLayout     Makes the page.
     * @param ContentForm    $contentForm    The form, shown again on a refusal.
     * @param ContentRequest $contentRequest Checks the slug.
     * @param PostedFields   $postedFields   Reads the slug.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private ContentForm $contentForm,
        private ContentRequest $contentRequest,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @return PageResponse 303 to the editor, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $slug = $this->postedFields->value($serverRequest, 'slug');

        if (!$this->contentRequest->isSlug($slug)) {
            $error = 'A slug is lower-case words and digits joined by hyphens, such as about-us.';

            return $this->htmlLayout->content([new Heading('Pages'), $this->contentForm->starter($serverRequest, $slug, $error)], 422);
        }

        return new PageRedirect('/account/pages/' . $slug);
    }
}
