<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Application\PageHandler;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Controller\Contracts\Page;
use AIC\Database\Contracts\Database;
use AIC\Entity\Application\EntityStore;
use App\Note\NoteRepository;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The note pages, each wrapped in the Page handler, by the handler id
 * config/routes.php names.
 */
final readonly class NoteHandlers
{
    /**
     * @param HtmlLayout         $htmlLayout         The document around each page.
     * @param Psr7ResponseWriter $psr7ResponseWriter Writes the pages' responses.
     * @param Database           $database           The database.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private Psr7ResponseWriter $psr7ResponseWriter,
        private Database $database,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $noteRepository = new NoteRepository(new EntityStore($this->database));
        $noteRequest = new NoteRequest();
        $noteViews = new NoteViews($this->htmlLayout);

        return [
            NoteCreatePost::class => $this->handler(new NoteCreatePost($noteRepository, $noteRequest, $noteViews)),
            NoteDeletePost::class => $this->handler(new NoteDeletePost($noteRepository, $noteRequest, $noteViews)),
            NoteListPage::class => $this->handler(new NoteListPage($noteRepository, $noteViews)),
            NoteShowPage::class => $this->handler(new NoteShowPage($noteRepository, $noteRequest, $noteViews)),
            NoteUpdatePost::class => $this->handler(new NoteUpdatePost($noteRepository, $noteRequest, $noteViews)),
        ];
    }

    /**
     * @param Page $page A note page.
     *
     * @return RequestHandlerInterface It, as a PSR-15 handler.
     */
    private function handler(Page $page): RequestHandlerInterface
    {
        return new PageHandler($page, $this->psr7ResponseWriter);
    }
}
