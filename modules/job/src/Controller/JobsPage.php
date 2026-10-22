<?php

declare(strict_types=1);

namespace App\Job\Controller;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use AIC\Queue\Application\PostgresQueue;
use AIC\Schedule\Application\Scheduler;
use AIC\Schedule\Domain\Schedule;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function htmlspecialchars;
use function implode;

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
        $html = (new Button('Queue a job (retries once, then succeeds)', 'submit', 'queue-succeed'))->render();
        $fail = (new Button('Queue a failing job', 'submit', 'queue-fail'))->render();

        return $this->htmlLayout->content(
            '<h2>Schedules</h2>' . self::items('schedules', array_map($this->scheduleLine(...), $this->schedules))
            . '<h2>Waiting</h2>' . self::items('waiting', $this->postgresQueue->waiting())
            . '<h2>Failed</h2>' . self::items('failed', $this->postgresQueue->failed())
            . (new Form('', '/jobs?mode=succeed', $html, $formToken))->render()->markup()
            . (new Form('', '/jobs?mode=fail', $fail, $formToken))->render()->markup(),
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

    /**
     * @param string       $id    The list's element id.
     * @param list<string> $lines Its lines.
     *
     * @return string The list, escaped; a line saying it is empty when it is.
     */
    private static function items(string $id, array $lines): string
    {
        if ($lines === []) {
            return '<p id="' . $id . '">None.</p>';
        }

        return '<ul id="' . $id . '">' . implode('', array_map(
            static fn (string $line): string => '<li>' . htmlspecialchars($line) . '</li>',
            $lines,
        )) . '</ul>';
    }
}
