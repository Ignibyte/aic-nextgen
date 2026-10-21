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
use App\Generated\ModuleHandlers;
use App\Greeting\GreetingHandlers;
use App\Note\NoteHandlers;
use App\Session\VisitHandlers;
use App\Site\HtmlLayout;
use App\Site\SiteHandlers;
use Psr\Http\Server\RequestHandlerInterface;

use function array_merge;

/**
 * The app's controllers, each wrapped in its kind's handler, by the handler id
 * config/routes.php names, gathered from NoteHandlers, VisitHandlers,
 * GreetingHandlers, SiteHandlers and DatabaseFeatureHandlers, and the modules'
 * from the generated ModuleHandlers (#92).
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
        $visitHandlers = new VisitHandlers($htmlLayout, $pageHandlers);
        $greetingHandlers = new GreetingHandlers($htmlLayout, $pageHandlers, $this->appConfig, $this->bootedAt, $this->greeters);
        $siteHandlers = new SiteHandlers($psr7ResponseWriter, $this->appConfig, $this->bootedAt, $this->root, $this->database);
        $databaseFeatureHandlers = new DatabaseFeatureHandlers($htmlLayout, $pageHandlers, $this->database, $this->envelopeCodec);
        $moduleHandlers = $this->moduleHandlers($pageHandlers, $psr7ResponseWriter);

        return array_merge(
            $noteHandlers->byId(),
            $visitHandlers->byId(),
            $greetingHandlers->byId(),
            $siteHandlers->byId(),
            $databaseFeatureHandlers->byId(),
            $moduleHandlers->byId(),
        );
    }

    /**
     * @param PageHandlers       $pageHandlers       Wraps each page with the response writer and the blocks.
     * @param Psr7ResponseWriter $psr7ResponseWriter Writes the responses as PSR-7.
     *
     * @return ModuleHandlers The modules' handlers, from the generated wiring (#92).
     */
    private function moduleHandlers(PageHandlers $pageHandlers, Psr7ResponseWriter $psr7ResponseWriter): ModuleHandlers
    {
        return new ModuleHandlers(
            $pageHandlers,
            $psr7ResponseWriter,
            $this->appConfig,
            $this->database,
            $this->envelopeCodec,
            $this->bootedAt,
            $this->root,
        );
    }
}
