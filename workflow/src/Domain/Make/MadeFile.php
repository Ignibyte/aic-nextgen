<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function hash;

/**
 * One file a make writes (#122): its path, relative to the project, and its
 * bytes; for a file it edits in place (the manifest, a module's types, #328),
 * the bytes it had, which a failed make writes back.
 */
final readonly class MadeFile
{
    /**
     * @param string      $path     Relative to the project's root.
     * @param string      $contents Its bytes.
     * @param string|null $original The bytes it had, for a file the make edits; null for one it creates.
     */
    public function __construct(
        public string $path,
        public string $contents,
        public ?string $original = null,
    ) {
    }

    /**
     * @return bool Whether the make creates it, rather than editing a file that is there.
     */
    public function created(): bool
    {
        return $this->original === null;
    }

    /**
     * @return string The hash of its bytes, `xxh128:<hex>`, which `code.made` and the answer carry.
     */
    public function hash(): string
    {
        return 'xxh128:' . hash('xxh128', $this->contents);
    }
}
