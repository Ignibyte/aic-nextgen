<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function hash;

/**
 * One file a make writes (#122): its path, relative to the project, and its
 * bytes.
 */
final readonly class MadeFile
{
    /**
     * @param string $path     Relative to the project's root.
     * @param string $contents Its bytes.
     */
    public function __construct(
        public string $path,
        public string $contents,
    ) {
    }

    /**
     * @return string The hash of its bytes, `xxh128:<hex>`, which `code.made` and the answer carry.
     */
    public function hash(): string
    {
        return 'xxh128:' . hash('xxh128', $this->contents);
    }
}
