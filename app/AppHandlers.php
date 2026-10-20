<?php

declare(strict_types=1);

namespace App;

use AIC\Block\Contracts\Blocks;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\PageHandlers;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Http\Application\ResponseBuilder;
use App\Auth\AuthHandlers;
use App\Greeting\GreetingHandlers;
use App\Job\JobHandlers;
use App\Note\NoteHandlers;
use App\Session\VisitHandlers;
use App\Site\ApiHandlers;
use App\Site\HtmlLayout;
use App\Site\StreamHandlers;
use Psr\Http\Server\RequestHandlerInterface;

use function array_merge;

/**
 * The app's controllers, each wrapped in its kind's handler, by the handler id
 * config/routes.php names, gathered from NoteHandlers, StreamHandlers,
 * VisitHandlers, GreetingHandlers, AuthHandlers, JobHandlers and ApiHandlers.
 */
final readonly class AppHandlers
{
    /**
     * @param AppConfig       $appConfig       The app's settings.
     * @param ResponseBuilder $responseBuilder Makes the PSR-7 responses.
     * @param string          $bootedAt        When the app booted to serve this request, ISO 8601 in UTC.
     * @param string          $root            The app's root directory.
     * @param list<object>    $greeters        The Greeter plugins.
     * @param Database        $database        The database.
     * @param Blocks          $blocks          The app's blocks, placed in each page.
     * @param EnvelopeCodec   $envelopeCodec   Seals and opens the forms' signed envelopes.
     */
    public function __construct(
        private AppConfig $appConfig,
        private ResponseBuilder $responseBuilder,
        private string $bootedAt,
        private string $root,
        private array $greeters,
        private Database $database,
        private Blocks $blocks,
        private EnvelopeCodec $envelopeCodec,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $htmlLayout = new HtmlLayout($this->appConfig);
        $psr7ResponseWriter = new Psr7ResponseWriter($this->responseBuilder);
        $pageHandlers = new PageHandlers($psr7ResponseWriter, $this->blocks);
        $noteHandlers = new NoteHandlers($htmlLayout, $pageHandlers, $this->database, $this->envelopeCodec);
        $streamHandlers = new StreamHandlers($psr7ResponseWriter, $this->root);
        $visitHandlers = new VisitHandlers($htmlLayout, $pageHandlers);
        $greetingHandlers = new GreetingHandlers($htmlLayout, $pageHandlers, $this->appConfig, $this->bootedAt, $this->greeters);
        $authHandlers = new AuthHandlers($htmlLayout, $pageHandlers, $this->database);
        $jobHandlers = new JobHandlers($htmlLayout, $pageHandlers, $this->database);
        $apiHandlers = new ApiHandlers($psr7ResponseWriter, $this->appConfig, $this->bootedAt, $this->database);

        return array_merge(
            array_merge($noteHandlers->byId(), $streamHandlers->byId()),
            array_merge(
                array_merge($visitHandlers->byId(), $greetingHandlers->byId()),
                array_merge(array_merge($authHandlers->byId(), $jobHandlers->byId()), $apiHandlers->byId()),
            ),
        );
    }
}
