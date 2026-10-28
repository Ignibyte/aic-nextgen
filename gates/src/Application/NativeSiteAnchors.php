<?php

declare(strict_types=1);

namespace Gates\Application;

use Dom\Element;
use Dom\HTMLDocument;
use Gates\Domain\HttpClient;

use function array_unshift;
use function explode;
use function implode;
use function preg_replace_callback;
use function str_contains;
use function str_starts_with;
use function stripos;

use const LIBXML_NOERROR;

/**
 * gate:23's anchors (#188): a step may send `Tessera-Targets: @NAME`, as a page's link does before
 * Tessera resolves it. Each site resolves the step's anchors from its own page, as the browser would:
 * it reads the step's path whole, finds the element carrying `data-t-anchor="NAME"`, and writes its
 * path, the region and every `data-t-c` key from the outside in. An anchor the page lacks stays as
 * written, so the site's answer shows it.
 */
final readonly class NativeSiteAnchors
{
    /** An anchor in a targets list: `@` and a name in the key grammar. */
    private const string ANCHOR = '/@([A-Za-z0-9_.:]+(?:-[A-Za-z0-9_.:]+)*)/';

    /** The header line Tessera names its targets in. */
    private const string TARGETS = 'tessera-targets:';

    /**
     * @param HttpClient $httpClient Reads the page the anchors are on.
     */
    public function __construct(
        private HttpClient $httpClient,
    ) {
    }

    /**
     * @param string $origin `http://host:port` of the site.
     * @param string $path   The step's path, whose page holds the anchors.
     * @param string $header The step's header lines, its session cookie included.
     *
     * @return string The lines, each `@NAME` in the targets replaced by the anchored component's path.
     */
    public function resolved(string $origin, string $path, string $header): string
    {
        if (!str_contains($header, '@')) {
            return $header;
        }

        $lines = explode("\r\n", $header);
        $page = null;

        foreach ($lines as $index => $line) {
            if (stripos($line, self::TARGETS) !== 0 || !str_contains($line, '@')) {
                continue;
            }

            $page ??= $this->page($origin, $path, $lines);
            $lines[$index] = self::replaced($line, $page);
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param string       $origin The site.
     * @param string       $path   The page's path.
     * @param list<string> $lines  The step's header lines.
     *
     * @return HTMLDocument The page, read whole with the step's session cookie and no Tessera header.
     */
    private function page(string $origin, string $path, array $lines): HTMLDocument
    {
        $cookie = '';

        foreach ($lines as $line) {
            $cookie = str_starts_with($line, 'Cookie:')
                ? $line
                : $cookie;
        }

        $body = $this->httpClient->send($origin, 'GET', $path, '', $cookie)->body;

        return HTMLDocument::createFromString($body, LIBXML_NOERROR);
    }

    /**
     * @param string       $line         A `Tessera-Targets` line.
     * @param HTMLDocument $htmlDocument The page.
     *
     * @return string The line, each anchor the page holds written as Tessera 0.3.0 sends it: its component's path,
     *                then the anchor (protocol 2, #194).
     */
    private static function replaced(string $line, HTMLDocument $htmlDocument): string
    {
        return self::each(
            self::ANCHOR,
            $line,
            static fn (string $name, string $whole): string => self::entryOf($htmlDocument, $name, $whole),
        );
    }

    /**
     * @param HTMLDocument $htmlDocument The page.
     * @param string       $name         An anchor's name.
     * @param string       $whole        The anchor as the step wrote it, `@NAME`.
     *
     * @return string `path @NAME` for the component carrying it; the anchor as written when none does.
     */
    private static function entryOf(HTMLDocument $htmlDocument, string $name, string $whole): string
    {
        $path = self::pathOf($htmlDocument, $name);

        return $path === null
            ? $whole
            : $path . ' ' . $whole;
    }

    /**
     * @param non-empty-string                 $pattern A pattern with one group.
     * @param string                           $text    The text.
     * @param callable(string, string): string $replace Gives each match's replacement from its group and itself.
     *
     * @return string The text, each match replaced.
     */
    private static function each(string $pattern, string $text, callable $replace): string
    {
        return preg_replace_callback(
            $pattern,
            static fn (array $match): string => $replace($match[1] ?? '', $match[0] ?? ''),
            $text,
        ) ?? $text;
    }

    /**
     * @param HTMLDocument $htmlDocument The page.
     * @param string       $name         An anchor's name, in the key grammar.
     *
     * @return string|null The path of the component carrying it; null when none does.
     */
    private static function pathOf(HTMLDocument $htmlDocument, string $name): ?string
    {
        $element = $htmlDocument->querySelector('[data-t-anchor="' . $name . '"]');

        return $element instanceof Element
            ? self::path($element)
            : null;
    }

    /**
     * @param Element $element A component's element.
     *
     * @return string Its path: the nearest region (`page` when none), then every `data-t-c` key from the outside in.
     */
    private static function path(Element $element): string
    {
        $keys = [];
        $node = $element;

        while ($node instanceof Element && !$node->hasAttribute('data-region')) {
            if ($node->hasAttribute('data-t-c')) {
                array_unshift($keys, (string) $node->getAttribute('data-t-c'));
            }

            $node = self::parentOf($node);
        }

        $region = $node instanceof Element
            ? (string) $node->getAttribute('data-region')
            : 'page';

        return $region . '/' . implode('/', $keys);
    }

    /**
     * @param Element $element An element.
     *
     * @return Element|null Its parent element; null at the top.
     */
    private static function parentOf(Element $element): ?Element
    {
        return $element->parentElement instanceof Element
            ? $element->parentElement
            : null;
    }
}
