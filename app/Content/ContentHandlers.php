<?php

declare(strict_types=1);

namespace App\Content;

use AIC\Controller\Application\PageHandlers;
use AIC\Database\Contracts\Database;
use App\Site\HtmlLayout;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The content pages' controllers (#50), each wrapped in the Page handler, by
 * the handler id config/routes.php names.
 */
final readonly class ContentHandlers
{
    /**
     * @param HtmlLayout   $htmlLayout   Makes the pages.
     * @param PageHandlers $pageHandlers Wraps each page with the response writer and the blocks.
     * @param Database     $database     Holds the pages.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PageHandlers $pageHandlers,
        private Database $database,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $contentRepository = new ContentRepository($this->database);
        $contentRequest = new ContentRequest();
        $contentForm = new ContentForm();
        $layout = $this->htmlLayout;

        $contentEditPage = new ContentEditPage($layout, $contentRepository, $contentRequest, $contentForm);
        $contentSavePost = new ContentSavePost($layout, $contentRepository, $contentRequest, $contentForm);

        return [
            ContentEditPage::class => $this->pageHandlers->handler($contentEditPage),
            ContentListPage::class => $this->pageHandlers->handler(new ContentListPage($layout, $contentRepository, $contentForm)),
            ContentNewPost::class => $this->pageHandlers->handler(new ContentNewPost($layout, $contentForm, $contentRequest)),
            ContentSavePost::class => $this->pageHandlers->handler($contentSavePost),
            ContentShowPage::class => $this->pageHandlers->handler(new ContentShowPage($layout, $contentRepository, $contentRequest)),
        ];
    }
}
