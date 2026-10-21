<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

use AIC\Controller\Contracts\ServerEvent;
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
    private const string TARGET = '#^[a-z][a-z0-9]*(-[a-z0-9]+)*(/[A-Za-z0-9_.:]+(-[A-Za-z0-9_.:]+)*)*$#';

    /** How a fragment lands (protocol 5.3). */
    private const array MODES = ['morph', 'inner', 'append', 'prepend', 'remove'];

    /**
     * @param string $target The target, `page` included.
     * @param string $markup What a fragment response's template would hold for it; ignored for `remove`.
     * @param string $mode   `morph`, `inner`, `append`, `prepend` or `remove`.
     * @param string $id     The event's id, for resuming after it; empty for none.
     * @param bool   $last   Whether the stream ends after it.
     *
     * @throws InvalidArgumentException When the target or the mode is not one Tessera reads.
     *
     * @return ServerEvent A `tessera-patch-elements` event.
     */
    public function patchElements(string $target, string $markup, string $mode, string $id, bool $last): ServerEvent
    {
        if (preg_match(self::TARGET, $target) !== 1 || !in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('A patch names a target and one of morph, inner, append, prepend or remove.');
        }

        $data = 'target ' . $target . ($mode === 'morph' ? '' : "\nmode " . $mode);

        if ($mode !== 'remove') {
            $data .= self::lines('elements', $markup);
        }

        return new ServerEvent('tessera-patch-elements', $data, $last, $id);
    }

    /**
     * @param string $scope         The target whose signal scope it patches; `page` for the document.
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
        if (preg_match(self::TARGET, $scope) !== 1) {
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
}
