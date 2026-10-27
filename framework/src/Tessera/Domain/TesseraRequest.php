<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

use AIC\Component\Contracts\Layout;

use function array_any;
use function explode;
use function in_array;
use function preg_match;
use function str_contains;
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
    public const string VERSION = '1';

    /** The kind of request: visit, submit, action, refresh or stream. */
    public const string REQUEST = 'Tessera-Request';

    /** The protocol the request speaks. */
    public const string PROTOCOL = 'Tessera-Protocol';

    /** The targets it wants. */
    public const string TARGETS = 'Tessera-Targets';

    /** The layout its page was rendered with. */
    public const string LAYOUT = 'Tessera-Layout';

    /** A write's id, the same on a browser's own repeat (protocol 5.8). */
    public const string WRITE_ID = 'Tessera-Write-Id';

    /** Where the browser must load in full (protocol 5.5). */
    public const string FULL_VISIT = 'Tessera-Full-Visit';

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
     * @param string $target A region or a component's path.
     *
     * @return string The props token the request sent for it in `Tessera-Targets`, unverified (#74); empty for none.
     */
    public function props(string $target): string
    {
        foreach (explode(',', $this->targets) as $entry) {
            $props = self::entryProps(trim($entry), $target);

            if ($props !== '') {
                return $props;
            }
        }

        return '';
    }

    /**
     * @param string $target A component's path.
     *
     * @return bool Whether it is a refresh, in AIC's protocol, of that one target alone: the one request a `204`
     *              may answer when the target has not changed (#193).
     */
    public function refreshesOnly(string $target): bool
    {
        return $this->kind === 'refresh'
            && $this->speaksVersion()
            && $this->targetList() === [$target];
    }

    /**
     * The targets a fragment answer renders for a visit, a refresh, a
     * submission or an action (protocol 5.1, 5.3). A visit also brings every
     * other region of the layout, so what lies outside its targets (the nav's
     * current link) follows the page (#71). The Renderer answers with the
     * whole document when one of them is not on the page (#98).
     *
     * @param Layout $layout The page.
     *
     * @return list<string> The targets in order; none when the whole document answers.
     */
    public function targets(Layout $layout): array
    {
        return $this->asksFragmentsOf($layout)
            ? $this->answered($layout)
            : [];
    }

    /**
     * @param Layout $layout The page.
     *
     * @return bool Whether it is a request Tessera commits fragments for, in AIC's protocol, from a page with the same layout.
     */
    private function asksFragmentsOf(Layout $layout): bool
    {
        return in_array($this->kind, ['visit', 'refresh', 'submit', 'action'], true)
            && $this->speaksVersion()
            && $this->layout === $layout->tesseraLayout();
    }

    /**
     * @param Layout $layout The page.
     *
     * @return list<string> The requested targets, then, for a visit naming a region (a navigation), the layout's
     *                      other regions apart from them; a visit naming only components gets exactly those (#83).
     */
    private function answered(Layout $layout): array
    {
        $targets = $this->targetList();

        if ($this->kind !== 'visit' || !self::namesRegion($targets)) {
            return $targets;
        }

        foreach ($layout->names() as $region) {
            if (self::overlaps($region, $targets)) {
                continue;
            }

            $targets[] = $region;
        }

        return $targets;
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
     * @param list<string> $targets The requested targets.
     *
     * @return bool Whether one is a region rather than a component path: Tessera's design answers a GET naming a
     *              component with that component, and the server may leave out what did not change.
     */
    private static function namesRegion(array $targets): bool
    {
        return array_any($targets, static fn (string $target): bool => !str_contains($target, '/'));
    }

    /**
     * @param string $entry  One `Tessera-Targets` entry: a target, then a space and its props.
     * @param string $target The target sought.
     *
     * @return string The entry's props when it names the target; empty otherwise.
     */
    private static function entryProps(string $entry, string $target): string
    {
        $parts = explode(' ', $entry, 2);

        return $parts[0] === $target
            ? $parts[1] ?? ''
            : '';
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
