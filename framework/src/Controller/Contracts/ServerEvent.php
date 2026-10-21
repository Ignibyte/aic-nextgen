<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use function explode;
use function str_replace;

/**
 * One Server-Sent Event: its name, data and id, and whether it is the last of
 * its stream. An event with no name is a comment line, the keep-alive Tessera
 * asks for at least every 15 seconds (protocol 7.1, #73).
 */
final readonly class ServerEvent
{
    /**
     * @param string $name The event's name, the client's `addEventListener()` type; empty for a comment.
     * @param string $data The data; each line becomes one `data:` field, or the comment's text.
     * @param bool   $last Whether the stream ends after this event.
     * @param string $id   Its id, which a reconnect sends back as `Last-Event-ID`; empty for none.
     */
    public function __construct(
        public string $name,
        public string $data,
        public bool $last,
        public string $id = '',
    ) {
    }

    /**
     * @return string The event on the wire: `event:`, `id:` when it has one, one `data:` per line, then a blank
     *                line; a comment is one `:` line and a blank line.
     */
    public function wire(): string
    {
        if ($this->name === '') {
            return ': ' . self::oneLine($this->data) . "\n\n";
        }

        $wire = 'event: ' . self::oneLine($this->name) . "\n";

        if ($this->id !== '') {
            $wire .= 'id: ' . self::oneLine($this->id) . "\n";
        }

        foreach (explode("\n", str_replace("\r", '', $this->data)) as $line) {
            $wire .= 'data: ' . $line . "\n";
        }

        return $wire . "\n";
    }

    /**
     * @param string $text A field's value.
     *
     * @return string It without line breaks, which would end the field.
     */
    private static function oneLine(string $text): string
    {
        return str_replace("\n", '', str_replace("\r", '', $text));
    }
}
