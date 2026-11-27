<?php

declare(strict_types=1);

namespace App\Statement\Controller;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Statement\Form\StatementForm;
use App\Statement\Form\StatementOrder;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /statements (#336): the order form for a statement PDF, which the
 * report worker makes from the queue.
 */
final readonly class StatementPage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     * @param StatementForm $statementForm The order form.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private StatementForm $statementForm = new StatementForm(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When the request carries no session, or the page layout is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->pageDocuments->content(
            [
                new Heading('Statements'),
                new Paragraph('A statement is a PDF the report worker makes from the queue, interpreted.', 'statement-about'),
                $this->statementForm->form($serverRequest, new StatementOrder('', ''), false),
            ],
            200,
            'Statements',
        );
    }
}
