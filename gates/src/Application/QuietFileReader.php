<?php

declare(strict_types=1);

namespace Gates\Application;

use function file_get_contents;
use function restore_error_handler;
use function set_error_handler;

/**
 * Reads a file for a gate, turning a read failure into `null` instead of a PHP warning.
 *
 * The gates report an unreadable file as their own finding, so the warning
 * `file_get_contents()` would raise is captured and the caller decides.
 */
final readonly class QuietFileReader
{
    /**
     * The file's contents, or null when it cannot be read.
     *
     * @param string $file Absolute path.
     *
     * @return string|null The contents.
     */
    public function read(string $file): ?string
    {
        set_error_handler(static fn (): bool => true);

        try {
            $contents = file_get_contents($file);
        } finally {
            restore_error_handler();
        }

        return $contents === false
            ? null
            : $contents;
    }
}
