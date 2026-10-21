<?php

declare(strict_types=1);

namespace App\Faq\Controller;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Faq\Component\FaqList;
use App\Faq\Service\FaqEntries;
use App\Site\HtmlLayout;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /faq: the questions about modules, in the app's layout. The first page
 * a module wires (#92): its route, its handler and its block come from
 * `modules/faq/faq.module.php` through the build.
 */
final readonly class FaqPage implements Page
{
    /**
     * @param HtmlLayout $htmlLayout The document around the page.
     * @param FaqEntries $faqEntries The questions.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private FaqEntries $faqEntries,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
     *
     * @throws InvalidArgumentException Never: `content` is a region the layout places.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->htmlLayout->page(new Regions([new Region('content', [new FaqList($this->faqEntries->all())])]), 200);
    }
}
