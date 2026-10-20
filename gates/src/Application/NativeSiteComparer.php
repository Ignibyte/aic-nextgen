<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\HttpClient;
use Gates\Domain\HttpExchange;
use Gates\Domain\NativeSiteSession;
use Gates\Domain\NativeSiteStep;

use function is_string;
use function mb_strtolower;
use function mb_trim;
use function parse_url;
use function preg_replace;
use function sprintf;

use const PHP_URL_PATH;

/**
 * Runs the step script against one site, and compares two sites' answers
 * (gate:23): status, content type, location and body, with what changes from
 * run to run normalised away: timestamps, note ids, and the host in a location.
 */
final readonly class NativeSiteComparer
{
    /**
     * The volatile values and what they read as once normalised.
     */
    private const array VOLATILE = [
        '#/notes/\\d+#' => '/notes/<id>',
        '/name="aic_csrf" value="[0-9a-f]{64}"/' => 'name="aic_csrf" value="<token>"',
        '/name="aic_envelope" value="[\\w.-]+"/' => 'name="aic_envelope" value="<envelope>"',
        '/\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z/' => '<time>',
        '/\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}(\\.\\d+)?[+-]\\d{2}(:\\d{2})?/' => '<time>',
    ];

    /**
     * @param HttpClient $httpClient Sends the requests.
     */
    public function __construct(
        private HttpClient $httpClient,
    ) {
    }

    /**
     * @param string               $origin   `http://host:port` of the site.
     * @param list<NativeSiteStep> $steps    The steps to send, in order.
     * @param string               $password The gate user's password, for `{password}`; empty when there is none.
     *
     * @return list<HttpExchange> One answer per step, normalised.
     */
    public function run(string $origin, array $steps, string $password): array
    {
        $nativeSiteSession = new NativeSiteSession($password);
        $exchanges = [];

        foreach ($steps as $step) {
            $exchange = $this->httpClient->send(
                $origin,
                $step->method,
                $nativeSiteSession->path($step),
                $nativeSiteSession->form($step),
                $nativeSiteSession->header($step),
            );
            $nativeSiteSession->absorb($step, $exchange, self::path($exchange->location));
            $exchanges[] = self::normalised($exchange);
        }

        return $exchanges;
    }

    /**
     * @param list<NativeSiteStep> $steps    The steps.
     * @param list<HttpExchange>   $expected The interpreted site's answers.
     * @param list<HttpExchange>   $actual   The compiled site's answers.
     *
     * @return list<string> One line per difference or wrong status; empty when the sites agree.
     */
    public function differences(array $steps, array $expected, array $actual): array
    {
        $differences = [];

        foreach ($steps as $index => $step) {
            $php = $expected[$index] ?? new HttpExchange(0, '', '', '');
            $native = $actual[$index] ?? new HttpExchange(0, '', '', '');

            foreach (self::compared($step, $php, $native) as $difference) {
                $differences[] = sprintf('%s (%s %s): %s', $step->name, $step->method, $step->path, $difference);
            }
        }

        return $differences;
    }

    /**
     * @param NativeSiteStep $nativeSiteStep The step.
     * @param HttpExchange   $php            The interpreted answer.
     * @param HttpExchange   $native         The compiled answer.
     *
     * @return list<string> What differs, or which status is not the step's.
     */
    private static function compared(NativeSiteStep $nativeSiteStep, HttpExchange $php, HttpExchange $native): array
    {
        $found = [];

        if ($php->status !== $nativeSiteStep->status) {
            $found[] = sprintf('PHP answered %d, not %d', $php->status, $nativeSiteStep->status);
        }

        if ($native->status !== $php->status) {
            $found[] = sprintf('status %d natively, %d in PHP', $native->status, $php->status);
        }

        if ($native->contentType !== $php->contentType) {
            $found[] = sprintf('content type "%s" natively, "%s" in PHP', $native->contentType, $php->contentType);
        }

        if ($native->location !== $php->location) {
            $found[] = sprintf('location "%s" natively, "%s" in PHP', $native->location, $php->location);
        }

        if ($native->body !== $php->body) {
            $found[] = 'the bodies differ';
        }

        return $found;
    }

    /**
     * @param HttpExchange $httpExchange An answer.
     *
     * @return HttpExchange The answer with its volatile values normalised.
     */
    private static function normalised(HttpExchange $httpExchange): HttpExchange
    {
        return new HttpExchange(
            $httpExchange->status,
            mb_strtolower(mb_trim($httpExchange->contentType)),
            self::volatile(self::path($httpExchange->location)),
            self::volatile($httpExchange->body),
        );
    }

    /**
     * @param string $text Text from an answer.
     *
     * @return string The text with timestamps and note ids normalised.
     */
    private static function volatile(string $text): string
    {
        foreach (self::VOLATILE as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }

    /**
     * @param string $location A `Location` value.
     *
     * @return string Its path; empty for none.
     */
    private static function path(string $location): string
    {
        $path = parse_url($location, PHP_URL_PATH);

        return is_string($path)
            ? $path
            : '';
    }
}
