<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
{uses}
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * GET {path}, made by bin/aic make:entity (#123): the newest `{table}` rows,
 * a paragraph each, oldest first.
 */
final readonly class {class}ListPage implements Page
{
    /**
     * The most rows it shows.
     */
    private const int SHOWN = 50;

    /**
{params}     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private {class}Repository ${variable}Repository,
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
        ${variable}Lines = array_map(
            static fn ({class} ${variable}): Paragraph => new Paragraph(${variable}->line(), ''),
            $this->{variable}Repository->latest(self::SHOWN),
        );

        return $this->pageDocuments->content([new Heading('{title}'), ...${variable}Lines], 200, '{title}');
    }
}
