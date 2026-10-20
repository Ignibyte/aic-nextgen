<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Block\Contracts\Blocks;
use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\ApiHandler;
use AIC\Controller\Application\PageHandlers;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use Psr\Http\Server\RequestHandlerInterface;

use function array_merge;

/**
 * The app's controllers, each wrapped in its kind's handler, by the handler id
 * config/routes.php names; the note pages come from NoteHandlers and the
 * Stream controllers from StreamHandlers.
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
     */
    public function __construct(
        private AppConfig $appConfig,
        private ResponseBuilder $responseBuilder,
        private string $bootedAt,
        private string $root,
        private array $greeters,
        private Database $database,
        private Blocks $blocks,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $htmlLayout = new HtmlLayout($this->appConfig);
        $greetingForm = new GreetingForm();
        $psr7ResponseWriter = new Psr7ResponseWriter($this->responseBuilder);

        $pageHandlers = new PageHandlers($psr7ResponseWriter, $this->blocks);
        $noteHandlers = new NoteHandlers($htmlLayout, $pageHandlers, $this->database);
        $streamHandlers = new StreamHandlers($psr7ResponseWriter, $this->root);

        return array_merge(array_merge($noteHandlers->byId(), $streamHandlers->byId()), [
            GreetingPage::class => $pageHandlers->handler(new GreetingPage($htmlLayout, $this->greeters)),
            GreetingPost::class => $pageHandlers->handler(new GreetingPost($htmlLayout, $greetingForm)),
            PlaceholderPage::class => $pageHandlers->handler(
                new PlaceholderPage($htmlLayout, $greetingForm, $this->appConfig, $this->bootedAt),
            ),
            StatusApi::class => new ApiHandler(new StatusApi($this->appConfig, $this->bootedAt, $this->database), $psr7ResponseWriter),
        ]);
    }
}
