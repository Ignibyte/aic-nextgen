<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Application\PageHandlers;
use AIC\Database\Contracts\Database;
use AIC\Queue\Application\PostgresQueue;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The jobs pages (#46), each wrapped in the Page handler, by the handler id
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

        return [
            JobsPage::class => $this->pageHandlers->handler(new JobsPage($this->htmlLayout, $postgresQueue)),
            JobsPost::class => $this->pageHandlers->handler(new JobsPost($postgresQueue)),
        ];
    }
}
