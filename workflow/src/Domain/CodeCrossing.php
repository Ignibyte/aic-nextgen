<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * A class-like named across the edge of an area (#108): the class-like, the
 * area that refers to it, and its own area.
 */
final readonly class CodeCrossing
{
    /**
     * @param string $target     The class-like named.
     * @param string $sourceArea The area of the file that names it, such as `module:note`.
     * @param string $targetArea Its own area, such as `system:Http` or `module:site`.
     */
    public function __construct(
        public string $target,
        public string $sourceArea,
        public string $targetArea,
    ) {
    }
}
