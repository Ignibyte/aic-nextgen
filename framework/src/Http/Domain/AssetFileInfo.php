<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use function strlen;

/** An immutable published byte snapshot that matches the compiled catalog. */
final readonly class AssetFileInfo
{
    /**
     * @param string $contents The complete verified bytes, independent of later publication changes.
     * @param int    $modified Its observed modification time from the same open file handle.
     */
    public function __construct(
        public string $contents,
        public int $modified,
    ) {
    }

    /** @return int The verified snapshot's byte count. */
    public function size(): int
    {
        return strlen($this->contents);
    }
}
