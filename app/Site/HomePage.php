<?php

declare(strict_types=1);

namespace App\Site;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Greeting\GreetingForm;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function explode;
use function htmlspecialchars;
use function urldecode;

/**
 * The home page at `/` (#49): a link to every demo, then what the app and
 * the request carried, and the greeting form.
 */
final readonly class HomePage implements Page
{
    /**
     * Every demo, by path: each shows one system at work.
     */
    private const array DEMOS = [
        '/account' => 'Account (sign-in, protected route)',
        '/api/status' => 'Status API (JSON)',
        '/download/hello.txt' => 'A download (byte ranges)',
        '/events?count=5' => 'Server-Sent Events',
        '/health' => 'Health check',
        '/hello/World' => 'Greeting plugins',
        '/jobs' => 'Jobs, the queue and the scheduler',
        '/notes' => 'Notes (entity CRUD, templates, signed forms)',
        '/notifications' => 'Notifications (mail and database)',
        '/uploads' => 'Uploads (checked by content)',
        '/visits' => 'Visits (sessions)',
    ];

    /**
     * @param HtmlLayout   $htmlLayout   The document around the page.
     * @param GreetingForm $greetingForm The form posting a name to /hello.
     * @param AppConfig    $appConfig    The app's settings.
     * @param string       $bootedAt     When the app booted to serve this request, ISO 8601 in UTC.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private GreetingForm $greetingForm,
        private AppConfig $appConfig,
        private string $bootedAt,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->htmlLayout->content(
            '<h2>Demos</h2>' . self::links()
            . '<h2>This request</h2><p>The AIC kernel is serving this request.</p><dl>'
            . self::item('app-name', 'App name (config/app.php)', $this->appConfig->name)
            . self::item('app-booted', 'App booted', $this->bootedAt)
            . self::item('request-method', 'Request method', $serverRequest->getMethod())
            . self::item('request-path', 'Request path', $serverRequest->getUri()->getPath())
            . '</dl><ul id="query">' . self::queryItems($serverRequest->getUri()->getQuery()) . '</ul>'
            . $this->greetingForm->render($serverRequest, '', ''),
            200,
        );
    }

    /**
     * @param string $id    The definition's element id.
     * @param string $term  The term.
     * @param string $value The value, unescaped.
     *
     * @return string One `<dt>` and `<dd>`.
     */
    private static function item(string $id, string $term, string $value): string
    {
        return '<dt>' . $term . '</dt><dd id="' . $id . '">' . htmlspecialchars($value) . '</dd>';
    }

    /**
     * @param string $query The raw query string.
     *
     * @return string One `<li>` per `name=value` pair, decoded and escaped.
     */
    private static function queryItems(string $query): string
    {
        $items = '';

        foreach ($query === '' ? [] : explode('&', $query) as $pair) {
            $items .= '<li>' . htmlspecialchars(urldecode($pair)) . '</li>';
        }

        return $items;
    }

    /**
     * @return string The list of every demo, each a link.
     */
    private static function links(): string
    {
        $links = '';

        foreach (self::DEMOS as $path => $label) {
            $links .= '<li><a class="aic-link" href="' . $path . '">' . $label . '</a></li>';
        }

        return '<ul id="demos">' . $links . '</ul>';
    }
}
