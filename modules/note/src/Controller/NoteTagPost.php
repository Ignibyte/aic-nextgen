<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Form\Application\PostedFields;
use App\Note\Repository\NoteRepository;
use App\Note\Repository\NoteTagRepository;
use App\Note\Service\NoteTagForms;
use App\Note\Service\NoteViews;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `POST /notes/tags` (#190): adds a tag to a note, once; a malformed post or
 * a note that does not exist gets the form again.
 */
final readonly class NoteTagPost implements Page
{
    /**
     * @param NoteRepository    $noteRepository    The notes, to check the note exists.
     * @param NoteTagRepository $noteTagRepository The tags.
     * @param NoteTagForms      $noteTagForms      The form.
     * @param NoteViews         $noteViews         The note pages' documents.
     * @param PostedFields      $postedFields      Reads the posted fields.
     */
    public function __construct(
        private NoteRepository $noteRepository,
        private NoteTagRepository $noteTagRepository,
        private NoteTagForms $noteTagForms,
        private NoteViews $noteViews,
        private PostedFields $postedFields,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When a read or the write fails.
     * @throws LogicException When the required application state is unavailable, or a constructed value is invalid.
     *
     * @return PageResponse 303 to the tags, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $noteId = $this->postedFields->value($serverRequest, 'note_id');
        $tag = $this->postedFields->value($serverRequest, 'tag');
        $problem = $this->noteTagForms->problem($noteId, $tag);

        try {
            if ($problem === '') {
                $this->noteRepository->find((int) $noteId);
                $this->noteTagRepository->add((int) $noteId, $tag);

                return new PageRedirect('/notes/tags');
            }
        } catch (EntityNotFoundException) {
            $problem = 'No note has that number.';
        }

        $form = $this->noteTagForms->form($serverRequest, $noteId, $tag, $problem);

        return $this->noteViews->page([new Heading('Tags'), $form], 422);
    }
}
