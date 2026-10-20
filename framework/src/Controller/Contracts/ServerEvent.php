<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use function explode;
use function str_replace;

/**
 * One Server-Sent Event: its name and data, and whether it is the last of its
 * stream. Event ids and `Last-Event-ID` reconnects come with #12.
 */
final readonly class ServerEvent
{
    /**
     * @param string $name The event's name, the client's `addEventListener()` type; `message` is the default.
     * @param string $data The data; each line becomes one `data:` field.
     * @param bool   $last Whether the stream ends after this event.
     */
    public function __construct(
        public string $name,
        public string $data,
        public bool $last,
    ) {
    }

    /**
     * @return string The event on the wire: `event:`, one `data:` per line, then a blank line.
     */
    public function wire(): string
    {
        $wire = 'event: ' . str_replace("\n", '', str_replace("\r", '', $this->name)) . "\n";

        foreach (explode("\n", str_replace("\r", '', $this->data)) as $line) {
            $wire .= 'data: ' . $line . "\n";
        }

        return $wire . "\n";
    }
}
