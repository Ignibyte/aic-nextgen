<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Note\Entity\NoteMark;
use App\Note\Repository\NoteMarkRepository;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * GET /note-marks, made by bin/aic make:entity (#123): the newest `note_marks` rows,
 * a paragraph each, oldest first.
 */
final readonly class NoteMarkListPage implements Page
{
    /**
     * The most rows it shows.
     */
    private const int SHOWN = 50;

    /**
     * @param PageDocuments      $pageDocuments      Makes the page.
     * @param NoteMarkRepository $noteMarkRepository Reads the rows.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private NoteMarkRepository $noteMarkRepository,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
     *
     * @throws QueryException           When the rows cannot be read.
     * @throws InvalidArgumentException When the page layout is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $noteMarkLines = array_map(
            static fn (NoteMark $noteMark): Paragraph => new Paragraph($noteMark->line(), ''),
            $this->noteMarkRepository->latest(self::SHOWN),
        );

        return $this->pageDocuments->content([new Heading('Note marks'), ...$noteMarkLines], 200, 'Note marks');
    }
}
