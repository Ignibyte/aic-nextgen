<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\PageHandlers;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The placeholder page and the greeting pages, each wrapped in the Page
 * handler, by the handler id config/routes.php names.
 */
final readonly class GreetingHandlers
{
    /**
     * @param HtmlLayout   $htmlLayout   Makes the pages.
     * @param PageHandlers $pageHandlers Wraps each page with the response writer and the blocks.
     * @param AppConfig    $appConfig    The app's settings.
     * @param string       $bootedAt     When the app booted to serve this request, ISO 8601 in UTC.
     * @param list<object> $greeters     The Greeter plugins.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PageHandlers $pageHandlers,
        private AppConfig $appConfig,
        private string $bootedAt,
        private array $greeters,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $greetingForm = new GreetingForm();

        return [
            GreetingPage::class => $this->pageHandlers->handler(new GreetingPage($this->htmlLayout, $this->greeters)),
            GreetingPost::class => $this->pageHandlers->handler(new GreetingPost($this->htmlLayout, $greetingForm)),
            PlaceholderPage::class => $this->pageHandlers->handler(
                new PlaceholderPage($this->htmlLayout, $greetingForm, $this->appConfig, $this->bootedAt),
            ),
        ];
    }
}
