<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Controller\Contracts\EventSource;
use Override;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function mb_strlen;
use function strlen;
use function substr;

use const SEEK_SET;

/**
 * The body of an event stream: a read-only PSR-7 stream that pulls the next
 * event from its source whenever its buffer is empty, so the emitter's chunk
 * loop sends each event as it comes. It reaches its end after the event marked
 * last.
 */
final class EventStreamBody implements StreamInterface
{
    /**
     * The wire bytes pulled but not read yet.
     */
    private string $buffer = '';

    /**
     * Whether the source has given its last event.
     */
    private bool $ended = false;

    /**
     * The bytes read so far.
     */
    private int $position = 0;

    /**
     * @param EventSource $eventSource Gives the events.
     */
    public function __construct(
        private readonly EventSource $eventSource,
    ) {
    }

    /**
     * @return string Every remaining event, pulled to the end.
     */
    #[Override]
    public function __toString(): string
    {
        return $this->getContents();
    }

    /**
     * Ends the stream: no more events are pulled.
     */
    #[Override]
    public function close(): void
    {
        $this->ended = true;
        $this->buffer = '';
    }

    /**
     * Ends the stream, as `close()` does. An event stream holds no resource, so
     * there is none to hand back.
     *
     * @return resource|null Null.
     */
    #[Override]
    public function detach(): mixed
    {
        $this->close();

        return null;
    }

    /**
     * An event stream's length stays open until its last event.
     *
     * @return int|null Null: the size is not known.
     */
    #[Override]
    public function getSize(): ?int
    {
        return null;
    }

    /**
     * @return int The bytes read so far.
     */
    #[Override]
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * @return bool Whether the last event has been read.
     */
    #[Override]
    public function eof(): bool
    {
        return $this->ended && $this->buffer === '';
    }

    /**
     * @return bool Never: events cannot be read twice.
     */
    #[Override]
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * @param int $offset Ignored.
     * @param int $whence Ignored.
     *
     * @throws RuntimeException Always: an event stream cannot seek.
     */
    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('An event stream cannot seek to ' . $offset . ' (' . $whence . ').');
    }

    /**
     * @throws RuntimeException Always: an event stream cannot rewind.
     */
    #[Override]
    public function rewind(): void
    {
        throw new RuntimeException('An event stream cannot rewind.');
    }

    /**
     * @return bool Never.
     */
    #[Override]
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * @param string $string Ignored.
     *
     * @throws RuntimeException Always: an event stream is read-only.
     */
    #[Override]
    public function write(string $string): int
    {
        throw new RuntimeException('An event stream is read-only; ' . mb_strlen($string) . ' characters refused.');
    }

    /**
     * @return bool Always.
     */
    #[Override]
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * @param int $length At most this many bytes.
     *
     * @return string The next bytes; empty at the end.
     */
    #[Override]
    public function read(int $length): string
    {
        if ($this->buffer === '' && !$this->ended) {
            $event = $this->eventSource->next();
            $this->buffer = $event->wire();
            $this->ended = $event->last;
        }

        $chunk = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, strlen($chunk));
        $this->position += strlen($chunk);

        return $chunk;
    }

    /**
     * @return string Every remaining event, pulled to the end.
     */
    #[Override]
    public function getContents(): string
    {
        $contents = '';

        while (!$this->eof()) {
            $contents .= $this->read(8_192);
        }

        return $contents;
    }

    /**
     * @param string|null $key A metadata key; null for all.
     *
     * @return mixed No metadata: an empty map, or null for a key.
     */
    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return $key === null
            ? []
            : null;
    }
}
