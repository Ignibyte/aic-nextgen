<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageDocument;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function explode;
use function htmlspecialchars;
use function urldecode;

/**
 * The placeholder page at `/`: what the worker and the request carried, and the
 * greeting form, until the app has pages of its own.
 */
final readonly class PlaceholderPage implements Page
{
    /**
     * @param HtmlLayout   $htmlLayout   The document around the page.
     * @param GreetingForm $greetingForm The form posting a name to /hello.
     * @param AppConfig    $appConfig    The app's settings.
     * @param string       $bootedAt     When this worker booted, ISO 8601 in UTC.
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
        $workerMode = array_key_exists('FRANKENPHP_WORKER', $serverRequest->getServerParams())
            ? 'yes'
            : 'no';

        return new PageDocument($this->htmlLayout->document(
            '<p>The FrankenPHP worker is serving through the AIC kernel.</p><dl>'
            . self::item('app-name', 'App name (config/app.php)', $this->appConfig->name)
            . self::item('worker-mode', 'Worker mode', $workerMode)
            . self::item('worker-booted', 'Worker booted', $this->bootedAt)
            . self::item('request-method', 'Request method', $serverRequest->getMethod())
            . self::item('request-path', 'Request path', $serverRequest->getUri()->getPath())
            . '</dl><ul id="query">' . self::queryItems($serverRequest->getUri()->getQuery()) . '</ul>'
            . $this->greetingForm->render('', ''),
        ));
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
}
