<?php

declare(strict_types=1);

namespace App\Job;

use AIC\Controller\Application\PageHandlers;
use AIC\Database\Contracts\Database;
use AIC\Notification\Application\DatabaseChannel;
use AIC\Notification\Application\Notifier;
use AIC\Queue\Application\PostgresQueue;
use AIC\Schedule\Application\Scheduler;
use App\AppSchedules;
use App\Site\HtmlLayout;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The jobs and notifications pages (#46, #48), each wrapped in the Page handler, by the handler id
 * config/routes.php names.
 */
final readonly class JobHandlers
{
    /**
     * @param HtmlLayout   $htmlLayout   Makes the pages.
     * @param PageHandlers $pageHandlers Wraps each page with the response writer and the blocks.
     * @param Database     $database     Holds the queue.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PageHandlers $pageHandlers,
        private Database $database,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $postgresQueue = new PostgresQueue($this->database);
        $schedules = (new AppSchedules())->all();
        $scheduler = new Scheduler($this->database, $postgresQueue, $schedules);

        return [
            JobsPage::class => $this->pageHandlers->handler(new JobsPage($this->htmlLayout, $postgresQueue, $scheduler, $schedules)),
            JobsPost::class => $this->pageHandlers->handler(new JobsPost($postgresQueue)),
            NotificationsPage::class => $this->pageHandlers->handler(
                new NotificationsPage($this->htmlLayout, new DatabaseChannel($this->database)),
            ),
            NotificationsPost::class => $this->pageHandlers->handler(new NotificationsPost(new Notifier($postgresQueue))),
        ];
    }
}
