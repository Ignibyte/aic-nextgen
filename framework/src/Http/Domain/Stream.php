<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use Override;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

use function count;
use function fclose;
use function feof;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use function get_resource_type;
use function in_array;
use function is_resource;
use function str_replace;
use function stream_get_contents;
use function stream_get_meta_data;

use const SEEK_SET;

/**
 * The PSR-7 stream over a PHP stream resource.
 *
 * Readable, writable and seekable are read from the resource's mode when the
 * stream is made. Closing or detaching leaves the stream unusable: every read,
 * write or seek then throws, and the size is null. The resource is held in a
 * one-slot list, empty once unusable: elephc types an untyped property by its
 * default, `null`, but a list slot keeps the resource's type.
 */
final class Stream implements StreamInterface
{
    use StreamChecks;

    /**
     * The `fopen()` modes that allow reading, without `b` or `t`.
     */
    private const array READABLE_MODES = ['r', 'r+', 'w+', 'a+', 'x+', 'c+'];

    /**
     * The `fopen()` modes that allow writing, without `b` or `t`.
     */
    private const array WRITABLE_MODES = ['r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'];

    /**
     * The resource in a one-slot list; empty once closed or detached.
     *
     * @var list<resource>
     */
    private array $handle;

    /**
     * Whether the stream can be read.
     */
    private bool $readable;

    /**
     * Whether the stream can be written.
     */
    private bool $writable;

    /**
     * Whether the stream can seek.
     */
    private bool $seekable;

    /**
     * The size in bytes; -1 when unknown.
     */
    private int $size;

    /**
     * @param list<resource> $handle One open stream resource, in a one-slot list.
     *
     * @throws RuntimeException When it is not exactly one open stream.
     */
    public function __construct(array $handle)
    {
        if (count($handle) !== 1 || get_resource_type($handle[0]) !== 'stream') {
            throw new RuntimeException('A stream needs one open stream resource.');
        }

        $this->handle = $handle;

        $meta = stream_get_meta_data($this->handle[0]);
        $mode = str_replace('t', '', str_replace('b', '', $meta['mode']));
        $this->readable = in_array($mode, self::READABLE_MODES, true);
        $this->writable = in_array($mode, self::WRITABLE_MODES, true);
        $this->seekable = $meta['seekable'];
        $this->size = $this->measuredSize();
    }

    /**
     * @return string The whole stream from the start; empty on any error, as PSR-7 requires.
     */
    #[Override]
    public function __toString(): string
    {
        try {
            if ($this->seekable) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Close the resource; the stream is unusable afterwards.
     */
    #[Override]
    public function close(): void
    {
        $handle = $this->detach();

        if (!is_resource($handle)) {
            return;
        }

        fclose($handle);
    }

    /**
     * @return resource|null The resource, or null when there was none; the stream is unusable afterwards.
     */
    #[Override]
    public function detach(): mixed
    {
        $resource = $this->handle === []
            ? null
            : $this->handle[0];
        $this->handle = [];
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;
        $this->size = -1;

        return $resource;
    }

    /**
     * @return int|null The size in bytes, or null when unknown.
     */
    #[Override]
    public function getSize(): ?int
    {
        if ($this->size < 0) {
            $this->size = $this->measuredSize();
        }

        return $this->size < 0
            ? null
            : $this->size;
    }

    /**
     * @throws RuntimeException When the stream is unusable or the position cannot be read.
     *
     * @return int The position of the pointer.
     */
    #[Override]
    public function tell(): int
    {
        $this->assertUsable();

        return self::requireInt(ftell($this->handle[0]), 'Unable to read the stream position.');
    }

    /**
     * @return bool Whether the pointer is at the end, or the stream is unusable.
     */
    #[Override]
    public function eof(): bool
    {
        return $this->handle === [] || feof($this->handle[0]);
    }

    /**
     * @return bool Whether the stream can seek.
     */
    #[Override]
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * @param int $offset The offset.
     * @param int $whence `SEEK_SET`, `SEEK_CUR` or `SEEK_END`.
     *
     * @throws RuntimeException When the stream cannot seek there.
     */
    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->assertUsable();

        if (!$this->seekable) {
            throw new RuntimeException('The stream cannot seek.');
        }

        self::requireZero(fseek($this->handle[0], $offset, $whence), 'Unable to seek in the stream.');
    }

    /**
     * Seek to the start.
     *
     * @throws RuntimeException When the stream cannot seek.
     */
    #[Override]
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * @return bool Whether the stream can be written.
     */
    #[Override]
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * @param string $string The bytes to write.
     *
     * @throws RuntimeException When the stream cannot be written.
     *
     * @return int The number of bytes written.
     */
    #[Override]
    public function write(string $string): int
    {
        $this->assertUsable();

        if (!$this->writable) {
            throw new RuntimeException('The stream cannot be written.');
        }

        $this->size = -1;

        return self::requireInt(fwrite($this->handle[0], $string), 'Unable to write to the stream.');
    }

    /**
     * @return bool Whether the stream can be read.
     */
    #[Override]
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * @param int $length The most bytes to read.
     *
     * @throws RuntimeException When the stream cannot be read or the length is negative.
     *
     * @return string The bytes read; empty for a length of 0.
     */
    #[Override]
    public function read(int $length): string
    {
        $handles = $this->readableHandles();

        if ($length < 0) {
            throw new RuntimeException('A read length must not be negative.');
        }

        if ($length === 0) {
            return '';
        }

        return self::requireString(fread($handles[0], $length), 'Unable to read from the stream.');
    }

    /**
     * @throws RuntimeException When the stream cannot be read.
     *
     * @return string The rest of the stream.
     */
    #[Override]
    public function getContents(): string
    {
        $handles = $this->readableHandles();

        return self::requireString(
            stream_get_contents($handles[0]),
            'Unable to read the rest of the stream.',
        );
    }

    /**
     * @param string|null $key One metadata key, or null for all of them.
     *
     * @return mixed The value for the key or null; all metadata, or [] once unusable.
     */
    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        if ($this->handle === []) {
            return $key === null
                ? []
                : null;
        }

        $meta = stream_get_meta_data($this->handle[0]);

        return $key === null ? $meta : $meta[$key] ?? null;
    }
}
