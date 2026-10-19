<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Domain\Stream;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function fopen;
use function fwrite;
use function preg_match;
use function restore_error_handler;
use function rewind;
use function set_error_handler;

/**
 * The PSR-17 stream factory.
 */
final readonly class StreamFactory implements StreamFactoryInterface
{
    /**
     * An `fopen()` mode PSR-17 accepts.
     */
    private const string MODE_PATTERN = '/^[rwaxc]\\+?[bt]?$/';

    /**
     * @param string $content The stream's initial content.
     *
     * @throws RuntimeException When no temporary stream can be opened.
     *
     * @return StreamInterface A readable, writable stream in memory, at its start.
     */
    #[Override]
    public function createStream(string $content = ''): StreamInterface
    {
        $resource = fopen('php://temp', 'r+b');

        if ($resource === false) {
            throw new RuntimeException('Unable to open php://temp.');
        }

        fwrite($resource, $content);
        rewind($resource);

        return new Stream([$resource]);
    }

    /**
     * @param string $filename The file to open.
     * @param string $mode     An `fopen()` mode.
     *
     * @throws InvalidArgumentException When the mode is invalid.
     * @throws RuntimeException         When the file cannot be opened.
     *
     * @return StreamInterface The stream over the file.
     */
    #[Override]
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if (preg_match(self::MODE_PATTERN, $mode) !== 1) {
            throw new InvalidArgumentException('Invalid stream mode: ' . $mode);
        }

        set_error_handler(static fn (): bool => true);

        try {
            $resource = fopen($filename, $mode);
        } finally {
            restore_error_handler();
        }

        if ($resource === false) {
            throw new RuntimeException('Unable to open the file: ' . $filename);
        }

        return new Stream([$resource]);
    }

    /**
     * @param resource $resource An open stream resource.
     *
     * @throws RuntimeException When it is not an open stream.
     *
     * @return StreamInterface The stream over it.
     */
    #[Override]
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream([$resource]);
    }
}
