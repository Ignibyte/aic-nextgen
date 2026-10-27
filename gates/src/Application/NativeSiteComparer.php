<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\HttpClient;
use Gates\Domain\HttpExchange;
use Gates\Domain\NativeSiteAnswer;
use Gates\Domain\NativeSiteSession;
use Gates\Domain\NativeSiteStep;

use function array_keys;
use function array_map;
use function array_unique;
use function is_string;
use function mb_strtolower;
use function mb_trim;
use function parse_url;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function strlen;

use const PHP_URL_PATH;

/**
 * Runs the step script against one site, and compares two sites' answers
 * (gate:23): status, content type, location and body, with what changes from
 * run to run normalised away at comparison: timestamps, note ids, and the host
 * in a location.
 */
final readonly class NativeSiteComparer
{
    /**
     * The volatile values and what they read as once normalised.
     */
    private const array VOLATILE = [
        '#/notes/\\d+#' => '/notes/<id>',
        '#<(ul|p) id="(waiting|failed|notifications|content-pages|stock)">.*?</\\1>#s' => '<$2-list>',
        '#data-t-props="[\\w.~+/=-]+"#' => 'data-t-props="<props>"',
        '/name="aic_csrf" value="[0-9a-f]{64}"/' => 'name="aic_csrf" value="<token>"',
        '/name="aic_envelope" value="[\\w.-]+"/' => 'name="aic_envelope" value="<envelope>"',
        '/name="tessera-csrf-token" content="[0-9a-f]{64}"/' => 'name="tessera-csrf-token" content="<token>"',
        '/\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z/' => '<time>',
        '/\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}(\\.\\d+)?[+-]\\d{2}(:\\d{2})?/' => '<time>',
    ];

    /**
     * @param HttpClient        $httpClient        Sends the requests.
     * @param NativeSiteAnchors $nativeSiteAnchors Resolves a step's `@NAME` targets from the site's page (#188).
     */
    public function __construct(
        private HttpClient $httpClient,
        private NativeSiteAnchors $nativeSiteAnchors,
    ) {
    }

    /**
     * @param string               $origin   `http://host:port` of the site.
     * @param list<NativeSiteStep> $steps    The steps to send, in order.
     * @param string               $password The gate user's password, for `{password}`; empty when there is none.
     *
     * @return list<NativeSiteAnswer> One answer per step, with the path it was sent to (#171), as sent: the
     *                                HTML lint reads it whole (#170).
     */
    public function run(string $origin, array $steps, string $password): array
    {
        $nativeSiteSession = new NativeSiteSession($password);
        $answers = [];

        foreach ($steps as $step) {
            $path = $nativeSiteSession->path($step);
            $exchange = $this->httpClient->send(
                $origin,
                $step->method,
                $path,
                $nativeSiteSession->form($step),
                $this->nativeSiteAnchors->resolved($origin, $path, $nativeSiteSession->header($step)),
            );
            $nativeSiteSession->absorb($step, $exchange, self::path($exchange->location));
            $captured = $step->captures
                ? self::path($exchange->location)
                : '';
            $answers[] = new NativeSiteAnswer($path, $exchange, $captured);
        }

        return $answers;
    }

    /**
     * @param list<NativeSiteStep>   $steps    The steps.
     * @param list<NativeSiteAnswer> $expected The interpreted site's answers.
     * @param list<NativeSiteAnswer> $actual   The compiled site's answers.
     *
     * @return list<string> One line per difference or wrong status; empty when the sites agree.
     */
    public function differences(array $steps, array $expected, array $actual): array
    {
        $differences = [];
        $phpCaptured = self::captures($expected);
        $nativeCaptured = self::captures($actual);

        foreach ($steps as $index => $step) {
            $php = self::normalised(self::exchange($expected[$index] ?? null), $phpCaptured);
            $native = self::normalised(self::exchange($actual[$index] ?? null), $nativeCaptured);

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

        return [...$found, ...self::headers($php, $native)];
    }

    /**
     * @param NativeSiteAnswer|null $nativeSiteAnswer A step's answer; null when the site gave none.
     *
     * @return HttpExchange Its exchange; status 0 and empty for none.
     */
    private static function exchange(?NativeSiteAnswer $nativeSiteAnswer): HttpExchange
    {
        return $nativeSiteAnswer instanceof NativeSiteAnswer
            ? $nativeSiteAnswer->httpExchange
            : new HttpExchange(0, '', '', '');
    }

    /**
     * @param list<NativeSiteAnswer> $answers One site's answers.
     *
     * @return list<string> The paths its capturing steps captured: the records that run created (#178).
     */
    private static function captures(array $answers): array
    {
        $captured = [];

        foreach ($answers as $answer) {
            if (strlen($answer->captured) < 2) {
                continue;
            }

            $captured[] = $answer->captured;
        }

        return $captured;
    }

    /**
     * @param HttpExchange $httpExchange An answer.
     * @param list<string> $captured     The paths its site captured, each read as `<captured>` (#178).
     *
     * @return HttpExchange The answer with its volatile values normalised.
     */
    private static function normalised(HttpExchange $httpExchange, array $captured): HttpExchange
    {
        $normal = static fn (string $text): string => self::volatile(self::uncaptured($text, $captured));

        return new HttpExchange(
            $httpExchange->status,
            mb_strtolower(mb_trim($httpExchange->contentType)),
            $normal(self::path($httpExchange->location)),
            $normal($httpExchange->body),
            '',
            array_map($normal, $httpExchange->headers),
        );
    }

    /**
     * @param string       $text     Text from an answer.
     * @param list<string> $captured The paths the site captured.
     *
     * @return string The text with each captured path, as a whole path segment, read as `<captured>`: each site
     *                creates its own record, with its own id, against the one database.
     */
    private static function uncaptured(string $text, array $captured): string
    {
        foreach ($captured as $path) {
            $text = preg_replace('#' . preg_quote($path, '#') . '(?![\\w-])#', '<captured>', $text) ?? $text;
        }

        return $text;
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

    /**
     * @param HttpExchange $php    The interpreted answer.
     * @param HttpExchange $native The compiled answer.
     *
     * @return list<string> Each compared header (#173) whose value differs, or that only one site sent.
     */
    private static function headers(HttpExchange $php, HttpExchange $native): array
    {
        $found = [];

        foreach (array_unique([...array_keys($php->headers), ...array_keys($native->headers)]) as $name) {
            $nativeValue = $native->headers[$name] ?? '';
            $phpValue = $php->headers[$name] ?? '';

            if ($nativeValue === $phpValue) {
                continue;
            }

            $found[] = sprintf('header %s "%s" natively, "%s" in PHP', $name, $nativeValue, $phpValue);
        }

        return $found;
    }
}
