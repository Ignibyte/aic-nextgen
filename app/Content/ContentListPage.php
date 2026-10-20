<?php

declare(strict_types=1);

namespace App\Content;

use AIC\Component\Domain\Link;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Site\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function implode;

/**
 * GET /pages: every content page, linked by title, and the form that starts
 * a new one (#50).
 */
final readonly class ContentListPage implements Page
{
    /**
     * @param HtmlLayout        $htmlLayout        Makes the page.
     * @param ContentRepository $contentRepository The pages.
     * @param ContentForm       $contentForm       The new-page form.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private ContentRepository $contentRepository,
        private ContentForm $contentForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws QueryException When the pages cannot be read.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $items = array_map(
            static fn (ContentPage $contentPage): string => '<li>'
                . (new Link($contentPage->title, '/pages/' . $contentPage->slug))->render()->markup() . '</li>',
            $this->contentRepository->all(),
        );
        $list = $items === []
            ? '<p id="content-pages">None yet.</p>'
            : '<ul id="content-pages">' . implode('', $items) . '</ul>';

        return $this->htmlLayout->content(
            '<h2>Pages</h2>' . $list . $this->contentForm->starter($serverRequest, '', '')->markup(),
            200,
        );
    }
}
