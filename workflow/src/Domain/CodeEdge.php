<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One edge in the code index (#106): `extends`, `implements`, `uses`,
 * `instantiates`, `calls` or `refers` from PHP; `routes_to`, `handles`,
 * `requires` or `exports` from the build's surface.
 */
final readonly class CodeEdge
{
    /**
     * @param string $source The symbol it starts at.
     * @param string $target The symbol or class it names.
     * @param string $kind   What it is.
     * @param string $file   The file it was read from.
     */
    public function __construct(
        public string $source,
        public string $target,
        public string $kind,
        public string $file,
    ) {
    }
}
