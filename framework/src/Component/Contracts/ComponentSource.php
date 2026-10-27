<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * What builds a part of a page when the part is rendered (#192): a module
 * service holding what it needs, its reads in `build()`. A
 * `SourcedComponent` carries it, and the render pass calls `build()` only
 * when it renders that part, so a Tessera request for one part never builds
 * another's data.
 */
interface ComponentSource
{
    /**
     * @return Component The part to render: neither keyed nor sourced, since the key stays on the wrapper.
     */
    public function build(): Component;
}
