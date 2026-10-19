<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\ApiHandler;
use AIC\Controller\Application\PageHandler;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Controller\Application\StreamHandler;
use AIC\Http\Application\ResponseBuilder;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The app's controllers, each wrapped in its kind's handler, by the handler id
 * config/routes.php names.
 */
final readonly class AppHandlers
{
    /**
     * @param AppConfig       $appConfig       The app's settings.
     * @param ResponseBuilder $responseBuilder Makes the PSR-7 responses.
     * @param string          $bootedAt        When this worker booted, ISO 8601 in UTC.
     * @param string          $resources       The app's resources directory.
     * @param list<object>    $greeters        The Greeter plugins.
     */
    public function __construct(
        private AppConfig $appConfig,
        private ResponseBuilder $responseBuilder,
        private string $bootedAt,
        private string $resources,
        private array $greeters,
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

        return [
            GreetingPage::class => new PageHandler(new GreetingPage($htmlLayout, $this->greeters), $psr7ResponseWriter),
            GreetingPost::class => new PageHandler(new GreetingPost($htmlLayout, $greetingForm), $psr7ResponseWriter),
            PlaceholderPage::class => new PageHandler(
                new PlaceholderPage($htmlLayout, $greetingForm, $this->appConfig, $this->bootedAt),
                $psr7ResponseWriter,
            ),
            ResourceDownload::class => new StreamHandler(new ResourceDownload($this->resources), $psr7ResponseWriter),
            StatusApi::class => new ApiHandler(new StatusApi($this->appConfig, $this->bootedAt), $psr7ResponseWriter),
        ];
    }
}
