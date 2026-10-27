<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\BrowserItem;

use function array_any;
use function array_filter;
use function array_keys;
use function array_slice;
use function array_values;
use function implode;
use function preg_match;
use function sprintf;

/**
 * The browser gate's reading of a spec (#174, Chad 2026-10-26: "anything front
 * end facing needs to be looked at with playwright cli"). A File Manifest that
 * touches a front-end path is a front-end change, which is looked at: each
 * `## Browser Check` item names one look, a Playwright test in
 * `tests/browser/`, is ticked once the look ran, and ends, after `saw:`, with
 * `— keep` (the test stays and guards against regressions) or `— drop`.
 */
final readonly class BrowserLooks
{
    /**
     * The paths a person sees through: a theme (in its assets, what a page loads: styles, scripts, images, fonts),
     * the framework's templates, the browser tests, the app's config (theme and Tessera pin), a module's blocks and
     * forms, and its controllers but the APIs.
     */
    private const array FRONT_END = [
        '#^themes/[^/]+/(?!assets/)#',
        '#^themes/[^/]+/assets/.*\\.(?:css|js|mjs|svg|png|jpe?g|gif|webp|avif|ico|woff2?)\\z#',
        '#^framework/templates/#',
        '#^tests/browser/#',
        '#^config/app\\.php\\z#',
        '#^modules/[^/]+/src/(?:Block|Form)/#',
        '#^modules/[^/]+/src/Controller/(?![^/]*Api\\.php\\z)#',
    ];

    /**
     * A look's test, named in an item.
     */
    private const string SPEC = '#(?<![\\w/.-])(tests/browser/[\\w./-]+\\.spec\\.(?:ts|js|mjs))(?![\\w.-])#';

    /**
     * The decision, after what the look saw.
     */
    private const string DECISION = '/saw:.*—\\s*(keep|drop)\\s*\\z/u';

    /**
     * @param list<string> $paths Manifest paths.
     *
     * @return list<string> The front-end ones.
     */
    public function frontEnd(array $paths): array
    {
        return array_values(array_filter($paths, self::isFrontEnd(...)));
    }

    /**
     * @param SpecContract $specContract The spec.
     *
     * @return list<BrowserItem> Its Browser Check items, in order.
     */
    public function items(SpecContract $specContract): array
    {
        $items = [];

        foreach ($specContract->browserItems() as $index => $text) {
            $ticked = preg_match('/^\\[[xX]\\]/', $text) === 1;
            $items[] = new BrowserItem($index + 1, self::captured(self::SPEC, $text), $ticked, self::captured(self::DECISION, $text));
        }

        return $items;
    }

    /**
     * @param SpecContract $specContract The spec at plan.
     *
     * @return list<string> What keeps a front-end change's spec from being a contract: no `browser_testable: Yes`,
     *                      no look, or an item that names no test.
     */
    public function planProblems(SpecContract $specContract): array
    {
        $frontEnd = $this->frontEnd($specContract->files());
        $items = $this->items($specContract);
        $problems = $frontEnd === [] || $specContract->browserTestable()
            ? []
            : [sprintf(
                'the File Manifest touches front-end paths (%s): set browser_testable: Yes; each front-end change is looked at '
                    . 'with the Playwright CLI.',
                implode(', ', array_slice($frontEnd, 0, 3)),
            )];

        return [...$problems, ...$this->named($items, $frontEnd !== [] || $specContract->browserTestable())];
    }

    /**
     * @param list<BrowserItem> $items  The items.
     * @param bool              $looked Whether the change must be looked at.
     *
     * @return list<string> A missing look, and each item that names no test.
     */
    private function named(array $items, bool $looked): array
    {
        $problems = $looked && $items === []
            ? [SpecContract::BROWSER . ' names no look: add one `- [ ] tests/browser/<name>.spec.ts — <what it shows>` item per look.']
            : [];

        foreach (array_keys(array_filter($items, static fn (BrowserItem $browserItem): bool => $browserItem->spec === '')) as $index) {
            $problems[] = sprintf('%s item %d names no tests/browser/<name>.spec.ts.', SpecContract::BROWSER, $index + 1);
        }

        return $problems;
    }

    /**
     * @param non-empty-string $pattern A pattern with one capture.
     * @param string           $text    An item.
     *
     * @return string The capture; empty when the pattern does not match.
     */
    private static function captured(string $pattern, string $text): string
    {
        preg_match($pattern, $text, $matches);

        return $matches[1] ?? '';
    }

    /**
     * @param string $path A manifest path.
     *
     * @return bool Whether a person sees through it.
     */
    private static function isFrontEnd(string $path): bool
    {
        return array_any(self::FRONT_END, static fn (string $pattern): bool => preg_match($pattern, $path) === 1);
    }
}
