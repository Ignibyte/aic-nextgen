<?php

declare(strict_types=1);

namespace App\Statement\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use AIC\Queue\Application\PostgresQueue;
use App\Statement\Form\StatementForm;
use App\Statement\Form\StatementOrder;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function mb_trim;

/**
 * POST /statements (#336): queues the order on the report worker's queue as
 * its `statement` job, then 303 to the statement's page under the job's id;
 * an order the worker cannot make answers the form again, 422.
 */
final readonly class StatementPost implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the refused page.
     * @param PostgresQueue $postgresQueue The queue the worker takes from.
     * @param PostedFields  $postedFields  Reads the order.
     * @param StatementForm $statementForm The order form.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private PostgresQueue $postgresQueue,
        private PostedFields $postedFields = new PostedFields(),
        private StatementForm $statementForm = new StatementForm(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the form guard checked its token.
     *
     * @throws JsonException  When the payload cannot be encoded.
     * @throws LogicException When the request carries no session, or the location or page layout is invalid.
     * @throws QueryException When the job cannot be queued.
     *
     * @return PageResponse 303 to the statement's page; 422 with the form for an order the worker cannot make.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $statementOrder = new StatementOrder(
            mb_trim($this->postedFields->value($serverRequest, 'account')),
            mb_trim($this->postedFields->value($serverRequest, 'date')),
        );

        if (!$statementOrder->valid()) {
            return $this->pageDocuments->content(
                [new Heading('Statements'), $this->statementForm->form($serverRequest, $statementOrder, true)],
                422,
                'Statements',
            );
        }

        return new PageRedirect('/statements/' . $this->postgresQueue->dispatchTo('report', 'statement', $statementOrder->values(), 0));
    }
}
