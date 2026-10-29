<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use AIC\Component\Contracts\Component;

use function explode;
use function str_replace;

/**
 * One Server-Sent Event: its name, data and id, and whether it is the last of
 * its stream. An event with no name is a comment line, the keep-alive Tessera
 * asks for at least every 15 seconds (protocol 7.1, #73). A last event that
 * resumes ends only the connection (#202): no `tessera-close` follows, so
 * Tessera reconnects with the last id it saw, and a live part need not hold a
 * server worker for as long as its page is open.
 */
final readonly class ServerEvent
{
    /**
     * @param string         $name     The event's name, the client's `addEventListener()` type; empty for a comment.
     * @param string         $data     The data; each line becomes one `data:` field, or the comment's text.
     * @param bool           $last     Whether the stream ends after this event.
     * @param string         $id       Its id, which a reconnect sends back as `Last-Event-ID`; empty for none.
     * @param Component|null $elements An element patch's component, rendered as `elements` lines after the data;
     *                                 null for any other event.
     * @param bool           $resumes  With `last`: the connection ends but the stream does not, and Tessera
     *                                 reconnects from `Last-Event-ID` (#202).
     */
    public function __construct(
        public string $name,
        public string $data,
        public bool $last,
        public string $id = '',
        public ?Component $elements = null,
        public bool $resumes = false,
    ) {
    }

    /**
     * @param string $elements The event's component rendered, any number of lines; ignored when it has none.
     *
     * @return string The event on the wire: `event:`, `id:` when it has one, one `data:` per line, the component's
     *                lines as `data: elements …`, then a blank line; a comment is one `:` line and a blank line.
     */
    public function wire(string $elements): string
    {
        if ($this->name === '') {
            return ': ' . self::oneLine($this->data) . "\n\n";
        }

        $wire = 'event: ' . self::oneLine($this->name) . "\n";

        if ($this->id !== '') {
            $wire .= 'id: ' . self::oneLine($this->id) . "\n";
        }

        $wire .= self::fields('', $this->data);

        if ($this->elements !== null) {
            $wire .= self::fields('elements ', $elements);
        }

        return $wire . "\n";
    }

    /**
     * @param string $key  What starts each field's value: empty, or `elements `.
     * @param string $text The value, any number of lines.
     *
     * @return string One `data:` field per line.
     */
    private static function fields(string $key, string $text): string
    {
        $fields = '';

        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            $fields .= 'data: ' . $key . $line . "\n";
        }

        return $fields;
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
