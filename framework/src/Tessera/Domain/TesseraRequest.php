<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

use AIC\Component\Contracts\Layout;

use function array_any;
use function array_slice;
use function count;
use function explode;
use function htmlspecialchars;
use function in_array;
use function preg_match;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * What a request tells Tessera's server side (#70): its kind, the protocol
 * it speaks, the targets it wants and the layout its page has (protocol 4.2).
 * A plain request has none of them. The headers are untrusted input: a target
 * list that breaks the grammar is answered with the whole document.
 */
final readonly class TesseraRequest
{
    /** The protocol AIC speaks. */
    public const string VERSION = '0';

    /** The kind of request: visit, submit, action, refresh or stream. */
    public const string REQUEST = 'Tessera-Request';

    /** The protocol the request speaks. */
    public const string PROTOCOL = 'Tessera-Protocol';

    /** The targets it wants. */
    public const string TARGETS = 'Tessera-Targets';

    /** The layout its page was rendered with. */
    public const string LAYOUT = 'Tessera-Layout';

    /** What every page response varies by (protocol 8). */
    public const string VARY = 'Tessera-Request, Tessera-Protocol, Tessera-Targets, Tessera-Layout';

    /** A region name, then component keys (protocol 2.1, 2.4). */
    private const string TARGET = '#^[a-z][a-z0-9]*(-[a-z0-9]+)*(/[A-Za-z0-9_.:]+(-[A-Za-z0-9_.:]+)*)*$#';

    /**
     * @param string $kind     `Tessera-Request`; empty for a plain request.
     * @param string $protocol `Tessera-Protocol`.
     * @param string $targets  `Tessera-Targets`; empty for the page.
     * @param string $layout   `Tessera-Layout`.
     */
    public function __construct(
        private string $kind,
        private string $protocol,
        private string $targets,
        private string $layout,
    ) {
    }

    /**
     * @return bool Whether Tessera sent it.
     */
    public function isTessera(): bool
    {
        return $this->kind !== '';
    }

    /**
     * @return bool Whether it speaks AIC's protocol version.
     */
    public function speaksVersion(): bool
    {
        return $this->protocol === self::VERSION;
    }

    /**
     * The fragment response's body for a visit or a refresh whose targets
     * all exist under the page's layout (protocol 5.1, 5.3).
     *
     * @param Layout $layout The page, rendered.
     *
     * @return string The title, the tracked assets and one template per target; empty when the whole document answers.
     */
    public function fragments(Layout $layout): string
    {
        if (!$this->asksFragmentsOf($layout)) {
            return '';
        }

        $templates = '';

        foreach ($this->targetList() as $target) {
            $markup = $this->markup($layout, $target);

            if ($markup === '') {
                return '';
            }

            $templates .= '<template data-target="' . $target . '">' . $markup . '</template>';
        }

        return $templates === ''
            ? ''
            : '<title>' . htmlspecialchars($layout->title()) . '</title>' . $layout->trackedAssets()->markup() . $templates;
    }

    /**
     * @param Layout $layout The page.
     *
     * @return bool Whether it is a visit or a refresh in AIC's protocol from a page with the same layout.
     */
    private function asksFragmentsOf(Layout $layout): bool
    {
        return in_array($this->kind, ['visit', 'refresh'], true)
            && $this->speaksVersion()
            && $this->layout === $layout->tesseraLayout();
    }

    /**
     * @return list<string> The targets in order, without their props (#74); empty when absent, malformed or overlapping.
     */
    private function targetList(): array
    {
        $targets = [];

        foreach (explode(',', $this->targets) as $entry) {
            $target = explode(' ', trim($entry))[0];

            if (strlen($target) > 512 || preg_match(self::TARGET, $target) !== 1 || self::overlaps($target, $targets)) {
                return [];
            }

            $targets[] = $target;
        }

        return $targets;
    }

    /**
     * @param Layout $layout The page.
     * @param string $target A region, or a component's path.
     *
     * @return string The target's markup as the page renders it; empty when the page has no such target.
     */
    private function markup(Layout $layout, string $target): string
    {
        $segments = explode('/', $target);
        $region = $segments[0];

        if (!in_array($region, $layout->names(), true)) {
            return '';
        }

        $html = $layout->regions()->html($region)->markup();

        return count($segments) === 1
            ? $html
            : (new ComponentRuns())->find($html, array_slice($segments, 1));
    }

    /**
     * @param string       $target A target.
     * @param list<string> $others The targets before it.
     *
     * @return bool Whether it equals, contains or lies in one of them, which Tessera refuses in one response.
     */
    private static function overlaps(string $target, array $others): bool
    {
        return array_any(
            $others,
            static fn (string $other): bool => $other === $target
                || str_starts_with($target, $other . '/')
                || str_starts_with($other, $target . '/'),
        );
    }
}
