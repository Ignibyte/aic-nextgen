<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

use AIC\Component\Contracts\Component;

/** An explicit pairing of a module's component data with its authored template. */
final readonly class ModuleTemplate implements ModuleDeclaration
{
    /**
     * @param class-string<Component> $component The owning module's component data class.
     * @param string                  $template  Its module-relative templates/components/ source.
     */
    public function __construct(
        public string $component,
        public string $template,
    ) {
    }
}
