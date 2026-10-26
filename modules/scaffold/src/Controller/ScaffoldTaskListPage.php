<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Scaffold\Entity\ScaffoldTask;
use App\Scaffold\Repository\ScaffoldTaskRepository;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * GET /scaffold-tasks, made by bin/aic make:entity (#123): the newest `scaffold_tasks` rows,
 * a paragraph each, oldest first.
 */
final readonly class ScaffoldTaskListPage implements Page
{
    /**
     * The most rows it shows.
     */
    private const int SHOWN = 50;

    /**
     * @param PageDocuments          $pageDocuments          Makes the page.
     * @param ScaffoldTaskRepository $scaffoldTaskRepository Reads the rows.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private ScaffoldTaskRepository $scaffoldTaskRepository,
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
        $scaffoldTaskLines = array_map(
            static fn (ScaffoldTask $scaffoldTask): Paragraph => new Paragraph($scaffoldTask->line(), ''),
            $this->scaffoldTaskRepository->latest(self::SHOWN),
        );

        return $this->pageDocuments->content([new Heading('Scaffold tasks'), ...$scaffoldTaskLines], 200);
    }
}
