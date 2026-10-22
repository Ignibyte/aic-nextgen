<?php

declare(strict_types=1);

namespace App\Content\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Content\Component\ContentPages;
use App\Content\Entity\ContentPage;
use App\Content\Form\ContentForm;
use App\Content\Repository\ContentRepository;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

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
        $links = array_map(
            static fn (ContentPage $contentPage): KeyedComponent => new KeyedComponent(
                $contentPage->slug,
                new Link($contentPage->title, '/pages/' . $contentPage->slug),
            ),
            $this->contentRepository->all(),
        );

        return $this->htmlLayout->content(
            [new Heading('Pages'), new ContentPages($links), $this->contentForm->starter($serverRequest, '', '')],
            200,
        );
    }
}
