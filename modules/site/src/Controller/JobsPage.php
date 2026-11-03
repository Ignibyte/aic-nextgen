<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\Heading;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use AIC\Queue\Application\PostgresQueue;
use AIC\Schedule\Domain\Schedule;
use App\Site\Service\ScheduleLines;
use App\Theme\Components\LineList\LineList;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /jobs: the schedules and their last runs (#47), the queue and the
 * failed record, and buttons that queue the demo job (#46). `bin/aic queue:work` runs
 * the jobs.
 */
final readonly class JobsPage implements Page
{
    /**
     * @param PageDocuments  $pageDocuments Makes the page.
     * @param PostgresQueue  $postgresQueue The queue.
     * @param ScheduleLines  $scheduleLines The schedules as lines.
     * @param list<Schedule> $schedules     The app's schedules.
     * @param FormTokens     $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private PostgresQueue $postgresQueue,
        private ScheduleLines $scheduleLines,
        private array $schedules,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws QueryException When the queue cannot be read.
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $guardFields = $this->formTokens->guard($serverRequest);
        $button = new Button('Queue a job (retries once, then succeeds)', 'submit', 'queue-succeed');
        $succeed = new Form('', '/jobs?mode=succeed', [$button], $guardFields);
        $fail = new Form('', '/jobs?mode=fail', [new Button('Queue a failing job', 'submit', 'queue-fail')], $guardFields);

        return $this->pageDocuments->content(
            [
                new Heading('Schedules'),
                new LineList('schedules', $this->scheduleLines->lines($this->schedules), 'None.'),
                new Heading('Waiting'),
                new LineList('waiting', $this->postgresQueue->waiting(), 'None.'),
                new Heading('Failed'),
                new LineList('failed', $this->postgresQueue->failed(), 'None.'),
                $succeed,
                $fail,
            ],
            200,
            'Jobs',
        );
    }
}
