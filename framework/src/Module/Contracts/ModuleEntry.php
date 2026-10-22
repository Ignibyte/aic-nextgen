<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * One bare-name declaration of a manifest (#93): a required module, a block, a
 * migration, an export, a protected prefix or a factory, by its section.
 *
 * Manifests build it; nothing in the framework does, so it is API for them.
 *
 * @api
 */
final readonly class ModuleEntry implements ModuleDeclaration
{
    /**
     * @param ModuleSection $moduleSection The section it belongs to.
     * @param string        $value         The module's name, the class or the path.
     */
    public function __construct(
        public ModuleSection $moduleSection,
        public string $value,
    ) {
    }
}
