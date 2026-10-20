<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use AIC\Queue\Application\PostgresQueue;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function htmlspecialchars;
use function implode;

/**
 * GET /jobs: queues the demo job (one that succeeds, one that keeps failing)
 * and shows the queue and the failed record (#46). `bin/aic queue:work` runs
 * the jobs.
 */
final readonly class JobsPage implements Page
{
    /**
     * @param HtmlLayout    $htmlLayout    Makes the page.
     * @param PostgresQueue $postgresQueue The queue.
     * @param FormTokens    $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PostgresQueue $postgresQueue,
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
            '<h2>Waiting</h2>' . self::items('waiting', $this->postgresQueue->waiting())
            . '<h2>Failed</h2>' . self::items('failed', $this->postgresQueue->failed())
            . (new Form('', '/jobs?mode=succeed', $html, $formToken))->render()->markup()
            . (new Form('', '/jobs?mode=fail', $fail, $formToken))->render()->markup(),
            200,
        );
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
