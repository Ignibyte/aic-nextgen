<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Scaffold\Entity\ScaffoldEvent;
use App\Scaffold\Repository\ScaffoldEventRepository;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * GET /scaffold-events, made by bin/aic make:entity (#123): the newest `scaffold_events` rows,
 * a paragraph each, oldest first.
 */
final readonly class ScaffoldEventListPage implements Page
{
    /**
     * The most rows it shows.
     */
    private const int SHOWN = 50;

    /**
     * @param PageDocuments           $pageDocuments           Makes the page.
     * @param ScaffoldEventRepository $scaffoldEventRepository Reads the rows.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private ScaffoldEventRepository $scaffoldEventRepository,
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
        $scaffoldEventLines = array_map(
            static fn (ScaffoldEvent $scaffoldEvent): Paragraph => new Paragraph($scaffoldEvent->line(), ''),
            $this->scaffoldEventRepository->latest(self::SHOWN),
        );

        return $this->pageDocuments->content([new Heading('Scaffold events'), ...$scaffoldEventLines], 200, 'Scaffold events');
    }
}
