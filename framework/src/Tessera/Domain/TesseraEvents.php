<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

use AIC\Component\Contracts\Component;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Controller\Contracts\SignalPatch;
use InvalidArgumentException;

use function explode;
use function in_array;
use function preg_match;
use function str_replace;

/**
 * Tessera's stream events (#73, protocol 7.2): a fragment for one target, a
 * signal patch, and the keep-alive comment. Every data line is `KEY VALUE`;
 * markup and JSON take one line each per line they hold.
 */
final readonly class TesseraEvents
{
    /** A target: `page`, a region, or a component's path (protocol 2.6). */
    private const string TARGET = SignalPatch::TARGET;

    /** An anchor as a target (#204, Tessera 0.3.1): `@` and a name in the key grammar, up to 64. */
    private const string ANCHOR = SignalPatch::ANCHOR;

    /** How a fragment lands (protocol 5.3). */
    private const array MODES = ['morph', 'inner', 'append', 'prepend', 'remove'];

    /**
     * @param string    $target    The target, `page` included, or `@NAME` for an anchored component (#204).
     * @param Component $component What lands there, rendered when the event goes on the wire (#115); ignored for
     *                             `remove`.
     * @param string    $mode      `morph`, `inner`, `append`, `prepend` or `remove`.
     * @param string    $id        The event's id, for resuming after it; empty for none.
     * @param bool      $last      Whether the stream ends after it.
     *
     * @throws InvalidArgumentException When the target or the mode is not one Tessera reads.
     *
     * @return ServerEvent A `tessera-patch-elements` event.
     */
    public function patchElements(
        string $target,
        Component $component,
        string $mode,
        string $id,
        bool $last,
    ): ServerEvent {
        if (!self::targets($target) || !in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('A patch names a target and one of morph, inner, append, prepend or remove.');
        }

        $data = 'target ' . $target . ($mode === 'morph' ? '' : "\nmode " . $mode);
        $elements = $mode === 'remove'
            ? null
            : $component;

        return new ServerEvent('tessera-patch-elements', $data, $last, $id, $elements);
    }

    /**
     * @param string $scope         The target whose signal scope it patches, `@NAME` included; `page` for the document.
     * @param string $json          A strict JSON object.
     * @param bool   $onlyIfMissing Whether it sets only signals that do not exist yet.
     * @param string $id            The event's id; empty for none.
     * @param bool   $last          Whether the stream ends after it.
     *
     * @throws InvalidArgumentException When the scope is not a target.
     *
     * @return ServerEvent A `tessera-patch-signals` event.
     */
    public function patchSignals(string $scope, string $json, bool $onlyIfMissing, string $id, bool $last): ServerEvent
    {
        if (!self::targets($scope)) {
            throw new InvalidArgumentException('A signal patch names a target as its scope.');
        }

        $data = 'scope ' . $scope . ($onlyIfMissing ? "\nonlyIfMissing true" : '') . self::lines('signals', $json);

        return new ServerEvent('tessera-patch-signals', $data, $last, $id);
    }

    /**
     * A source that waits longer than 15 seconds for its next event returns this meanwhile.
     *
     * @param bool $last Whether the stream ends after it, for a source with nothing left.
     *
     * @return ServerEvent The `: keep-alive` comment.
     */
    public function keepAlive(bool $last): ServerEvent
    {
        return new ServerEvent('', 'keep-alive', $last);
    }

    /**
     * A source that bounds each connection returns this when one has lasted long enough (#202): the connection
     * ends without `tessera-close`, and Tessera reconnects with the last event id it saw.
     *
     * @return ServerEvent The `: reconnect` comment, last and resuming.
     */
    public function reconnect(): ServerEvent
    {
        return new ServerEvent('', 'reconnect', true, '', null, true);
    }

    /**
     * @param string $key  The data key.
     * @param string $text Its value, any number of lines.
     *
     * @return string One `\nKEY LINE` per line of the text.
     */
    private static function lines(string $key, string $text): string
    {
        $data = '';

        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            $data .= "\n" . $key . ' ' . $line;
        }

        return $data;
    }

    /**
     * @param string $target A patch's target or a signal patch's scope.
     *
     * @return bool Whether Tessera reads it: a path, or `@NAME`, which Tessera resolves on the live page (#204).
     */
    private static function targets(string $target): bool
    {
        return preg_match(self::TARGET, $target) === 1 || preg_match(self::ANCHOR, $target) === 1;
    }
}
