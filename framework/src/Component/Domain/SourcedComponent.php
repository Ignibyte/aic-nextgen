<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;

/**
 * A part of a page built from its source when it is rendered (#192). It has
 * no template: the render pass renders what the source builds. Wrap it in a
 * `KeyedComponent` for a key, props or an anchor, so Tessera can ask for it
 * alone.
 */
final readonly class SourcedComponent implements Component
{
    /**
     * @param ComponentSource $componentSource Builds the part.
     */
    public function __construct(
        public ComponentSource $componentSource,
    ) {
    }
}
