<?php

declare(strict_types=1);

namespace App\Job\Controller;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\Heading;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use AIC\Queue\Application\PostgresQueue;
use AIC\Schedule\Application\Scheduler;
use AIC\Schedule\Domain\Schedule;
use App\Job\Component\LineList;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * GET /jobs: the schedules and their last runs (#47), the queue and the
 * failed record, and buttons that queue the demo job (#46). `bin/aic queue:work` runs
 * the jobs.
 */
final readonly class JobsPage implements Page
{
    /**
     * @param HtmlLayout     $htmlLayout    Makes the page.
     * @param PostgresQueue  $postgresQueue The queue.
     * @param Scheduler      $scheduler     Knows when each schedule last fired.
     * @param list<Schedule> $schedules     The app's schedules.
     * @param FormTokens     $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PostgresQueue $postgresQueue,
        private Scheduler $scheduler,
        private array $schedules,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws QueryException When the queue cannot be read.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $formToken = $this->formTokens->issue($serverRequest);
        $button = new Button('Queue a job (retries once, then succeeds)', 'submit', 'queue-succeed');
        $succeed = new Form('', '/jobs?mode=succeed', [$button], $formToken);
        $fail = new Form('', '/jobs?mode=fail', [new Button('Queue a failing job', 'submit', 'queue-fail')], $formToken);

        return $this->htmlLayout->content(
            [
                new Heading('Schedules'),
                new LineList('schedules', array_map($this->scheduleLine(...), $this->schedules), 'None.'),
                new Heading('Waiting'),
                new LineList('waiting', $this->postgresQueue->waiting(), 'None.'),
                new Heading('Failed'),
                new LineList('failed', $this->postgresQueue->failed(), 'None.'),
                $succeed,
                $fail,
            ],
            200,
        );
    }

    /**
     * @param Schedule $schedule A schedule.
     *
     * @throws QueryException When the ledger cannot be read.
     *
     * @return string Its name, cadence, job and last run.
     */
    private function scheduleLine(Schedule $schedule): string
    {
        $last = $this->scheduler->lastRun($schedule->name);

        return $schedule->name . ' (' . $schedule->cadence->expression . ') queues ' . $schedule->job
            . '; last run ' . ($last === '' ? 'never' : $last . ' UTC');
    }
}
