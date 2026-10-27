<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Note\Entity\NoteTag;
use App\Note\Repository\NoteTagRepository;
use App\Note\Service\NoteTagForms;
use App\Note\Service\NoteViews;
use App\Theme\Components\LineList\LineList;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function array_map;
use function is_string;

/**
 * `GET /notes/tags` (#190): every tag with the title of the note it tags, read
 * through a join, optionally searched (`q`) in the tags and the titles, and
 * the form that adds one.
 */
final readonly class NoteTagsPage implements Page
{
    /**
     * @param NoteTagRepository $noteTagRepository The tags.
     * @param NoteTagForms      $noteTagForms      The add-a-tag form.
     * @param NoteViews         $noteViews         The note pages' documents.
     */
    public function __construct(
        private NoteTagRepository $noteTagRepository,
        private NoteTagForms $noteTagForms,
        private NoteViews $noteViews,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with an optional `q`.
     *
     * @throws QueryException When the tags cannot be read.
     * @throws LogicException When the required application state is unavailable, or a constructed value is invalid.
     *
     * @return PageResponse The tags.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $query = $serverRequest->getQueryParams();
        $search = array_key_exists('q', $query) && is_string($query['q'])
            ? $query['q']
            : '';
        $lines = array_map(static fn (NoteTag $noteTag): string => $noteTag->line(), $this->noteTagRepository->tags($search));

        $counts = [];

        foreach ($this->noteTagRepository->counts() as $tag => $count) {
            $counts[] = $tag . ': ' . $count;
        }

        return $this->noteViews->page([
            new Heading('Tags'),
            new LineList('tags', $lines, 'No tags yet.'),
            new LineList('tag-counts', $counts, 'No tags yet.'),
            $this->noteTagForms->form($serverRequest, '', '', ''),
        ], 200);
    }
}
