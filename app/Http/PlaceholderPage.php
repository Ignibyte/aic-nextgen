<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function explode;
use function htmlspecialchars;
use function urldecode;

/**
 * The placeholder page at `/`: what the worker and the request carried, until
 * the app has pages of its own.
 */
final readonly class PlaceholderPage implements RequestHandlerInterface
{
    /**
     * @param HtmlResponder $htmlResponder The document and response.
     * @param AppConfig     $appConfig     The app's settings.
     * @param string        $bootedAt      When this worker booted, ISO 8601 in UTC.
     */
    public function __construct(
        private HtmlResponder $htmlResponder,
        private AppConfig $appConfig,
        private string $bootedAt,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The request.
     *
     * @return ResponseInterface The page.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $workerMode = array_key_exists('FRANKENPHP_WORKER', $request->getServerParams())
            ? 'yes'
            : 'no';

        return $this->htmlResponder->respond(
            '<p>The FrankenPHP worker is serving through the AIC kernel.</p><dl>'
            . self::item('app-name', 'App name (config/app.php)', $this->appConfig->name)
            . self::item('worker-mode', 'Worker mode', $workerMode)
            . self::item('worker-booted', 'Worker booted', $this->bootedAt)
            . self::item('request-method', 'Request method', $request->getMethod())
            . self::item('request-path', 'Request path', $request->getUri()->getPath())
            . '</dl><ul id="query">' . self::queryItems($request->getUri()->getQuery()) . '</ul>',
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
}
