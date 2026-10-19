<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use RuntimeException;

use function fstat;

/**
 * Stream's checks: the usable and readable assertions, the size, and the guards
 * that turn PHP's stream-function results (`int|false`, `string|false`, 0 for
 * success) into a typed value or a RuntimeException. A trait keeps Stream under
 * PHPMD's class-length limit; the using class holds `$handle` and `$readable`.
 */
trait StreamChecks
{
    /**
     * @throws RuntimeException When the stream is unusable or not readable.
     */
    private function assertReadable(): void
    {
        $this->assertUsable();

        if (!$this->readable) {
            throw new RuntimeException('The stream cannot be read.');
        }
    }

    /**
     * @throws RuntimeException When the stream is closed or detached.
     */
    private function assertUsable(): void
    {
        if ($this->handle === []) {
            throw new RuntimeException('The stream is closed or detached.');
        }
    }

    /**
     * @return int The size in bytes; -1 when unknown or unusable.
     */
    private function measuredSize(): int
    {
        if ($this->handle === []) {
            return -1;
        }

        $stats = fstat($this->handle[0]);

        return $stats === false
            ? -1
            : $stats['size'];
    }

    /**
     * @param int|false $value   A stream function's result, such as `ftell()` or `fwrite()`.
     * @param string    $message The exception message when it is `false`.
     *
     * @throws RuntimeException When the value is `false`.
     *
     * @return int The value.
     */
    private static function requireInt(int|false $value, string $message): int
    {
        if ($value === false) {
            throw new RuntimeException($message);
        }

        return $value;
    }

    /**
     * @param string|false $value   A stream function's result, such as `fread()`.
     * @param string       $message The exception message when it is `false`.
     *
     * @throws RuntimeException When the value is `false`.
     *
     * @return string The value.
     */
    private static function requireString(string|false $value, string $message): string
    {
        if ($value === false) {
            throw new RuntimeException($message);
        }

        return $value;
    }

    /**
     * @param int    $value   A stream function's result where 0 means success, such as `fseek()`.
     * @param string $message The exception message when it is not 0.
     *
     * @throws RuntimeException When the value is not 0.
     */
    private static function requireZero(int $value, string $message): void
    {
        if ($value !== 0) {
            throw new RuntimeException($message);
        }
    }
}
