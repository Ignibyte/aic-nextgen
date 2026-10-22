<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One generated template class (#98): its source, and whether its `render()`
 * takes the render pass, which it does only when the template writes a
 * component or a region, so the lookup calls it as it is declared.
 */
final readonly class TemplateClass
{
    /**
     * @param string $source    The class file.
     * @param bool   $takesPass Whether `render()` takes a `RenderPass` after the component.
     */
    public function __construct(
        public string $source,
        public bool $takesPass,
    ) {
    }
}
