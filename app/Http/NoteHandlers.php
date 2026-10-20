<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Application\PageHandlers;
use AIC\Database\Contracts\Database;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use App\Note\NoteRepository;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The note pages, each wrapped in the Page handler, by the handler id
 * config/routes.php names.
 */
final readonly class NoteHandlers
{
    /**
     * @param HtmlLayout    $htmlLayout    The document around each page.
     * @param PageHandlers  $pageHandlers  Wraps each page with the response writer and the blocks.
     * @param Database      $database      The database.
     * @param EnvelopeCodec $envelopeCodec Seals and opens the note forms' envelopes.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PageHandlers $pageHandlers,
        private Database $database,
        private EnvelopeCodec $envelopeCodec,
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
        $noteEnvelope = new NoteEnvelope($this->envelopeCodec);
        $noteForms = new NoteForms($noteEnvelope);

        return [
            NoteCreatePost::class => $this->pageHandlers->handler(
                new NoteCreatePost($noteRepository, $noteRequest, $noteViews, $noteForms),
            ),
            NoteDeletePost::class => $this->pageHandlers->handler(
                new NoteDeletePost($noteRepository, $noteRequest, $noteViews, $noteEnvelope),
            ),
            NoteListPage::class => $this->pageHandlers->handler(new NoteListPage($noteRepository, $noteViews, $noteForms)),
            NoteShowPage::class => $this->pageHandlers->handler(
                new NoteShowPage($noteRepository, $noteRequest, $noteViews, $noteForms),
            ),
            NoteUpdatePost::class => $this->pageHandlers->handler(
                new NoteUpdatePost($noteRepository, $noteRequest, $noteViews, $noteForms, $noteEnvelope),
            ),
        ];
    }
}
