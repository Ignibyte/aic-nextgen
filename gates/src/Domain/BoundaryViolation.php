<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One breach of the module boundaries gate:26 holds (#94): the path at fault,
 * relative to the repo root, and what to do about it.
 */
final readonly class BoundaryViolation
{
    /**
     * @param string $path    The file or folder, relative to the repo root.
     * @param string $message What is wrong and the fix, naming the folder, export or require it needs.
     */
    public function __construct(
        public string $path,
        public string $message,
    ) {
    }
}
