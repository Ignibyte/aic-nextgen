<?php

declare(strict_types=1);

namespace AIC\Http\Contracts;

use InvalidArgumentException;

use function preg_match;

/** Immutable build metadata for one explicitly public representation. */
final readonly class PublicAsset
{
    /**
     * @param string $name      The logical identifier: its owner, then its path below it (`theme_app/images/mark.svg`).
     * @param string $path      Its canonical /assets/ URL.
     * @param string $file      Its trusted path relative to build/assets/.
     * @param string $mediaType The declared response media type.
     * @param string $sha256    The lowercase SHA-256 of the published bytes.
     * @param int    $size      The published byte count.
     * @param string $aliasPath Its exact compatibility URL for the same representation.
     *
     * @throws InvalidArgumentException When compiled metadata is not canonical.
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $file,
        public string $mediaType,
        public string $sha256,
        public int $size,
        public string $aliasPath,
    ) {
        $this->address();
        $this->representation();
        $this->alias();
    }

    /**
     * @throws InvalidArgumentException When the name or file address is not portable and canonical.
     */
    private function address(): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]*(\\/[A-Za-z0-9_-][A-Za-z0-9_.-]*)+\\z/', $this->name) !== 1) {
            throw new InvalidArgumentException('A public asset needs an owner/path identifier.');
        }

        if (
            preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]*(\\/[A-Za-z0-9_-][A-Za-z0-9_.-]*)*\\z/', $this->file) !== 1
            || $this->path !== '/assets/' . $this->file
        ) {
            throw new InvalidArgumentException('A public asset needs a canonical portable file path.');
        }
    }

    /**
     * @throws InvalidArgumentException When the media type or byte identity is invalid.
     */
    private function representation(): void
    {
        if (preg_match('/^[a-z0-9.+-]+\\/[a-z0-9.+-]+(?:; charset=utf-8)?\\z/', $this->mediaType) !== 1) {
            throw new InvalidArgumentException('A public asset needs a declared media type.');
        }

        if ($this->size < 0 || preg_match('/^[a-f0-9]{64}\\z/', $this->sha256) !== 1) {
            throw new InvalidArgumentException('A public asset needs its byte count and SHA-256.');
        }
    }

    /**
     * @throws InvalidArgumentException When the compatibility URL is not portable and exact.
     */
    private function alias(): void
    {
        if (preg_match('#^/assets/[A-Za-z0-9_-][A-Za-z0-9_.-]*(/[A-Za-z0-9_-][A-Za-z0-9_.-]*)*\\z#', $this->aliasPath) !== 1) {
            throw new InvalidArgumentException('A public asset needs an exact portable compatibility URL.');
        }
    }
}
