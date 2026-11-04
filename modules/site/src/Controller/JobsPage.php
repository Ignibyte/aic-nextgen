<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use AIC\Schedule\Domain\Schedule;
use App\Site\Service\JobForms;
use App\Site\Service\QueueLines;
use App\Site\Service\ScheduleLines;
use App\Theme\Components\LineList\LineList;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_keys;
use function array_values;

/**
 * GET /jobs: the schedules and their last runs (#47), the queue and the
 * failed record through AIC's queue records with a Discard button per failed
 * job (#262), and buttons that queue the demo job (#46). `bin/aic queue:work`
 * runs the jobs.
 */
final readonly class JobsPage implements Page
{
    /**
     * @param PageDocuments  $pageDocuments Makes the page.
     * @param QueueLines     $queueLines    The queue and the failed record as lines.
     * @param ScheduleLines  $scheduleLines The schedules as lines.
     * @param list<Schedule> $schedules     The app's schedules.
     * @param FormTokens     $formTokens    Issues the visitor's CSRF token.
     * @param JobForms       $jobForms      The Discard and queue forms.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private QueueLines $queueLines,
        private ScheduleLines $scheduleLines,
        private array $schedules,
        private FormTokens $formTokens = new FormTokens(),
        private JobForms $jobForms = new JobForms(),
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
        $failed = $this->queueLines->failed();

        return $this->pageDocuments->content(
            [
                new Heading('Schedules'),
                new LineList('schedules', $this->scheduleLines->lines($this->schedules), 'None.'),
                new Heading('Waiting'),
                new LineList('waiting', $this->queueLines->queued(), 'None.'),
                new Heading('Failed'),
                new LineList('failed', array_values($failed), 'None.'),
                ...$this->jobForms->forms($this->formTokens->guard($serverRequest), array_keys($failed)),
            ],
            200,
            'Jobs',
        );
    }
}
