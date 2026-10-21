<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * A plugin a module contributes: a class registered under a plugin type with
 * the container's `tag()` (#5). Blocks are declared through `Module::blocks()`.
 *
 * Manifests are app code the build reads, so the framework itself never
 * constructs one; it is API for them.
 *
 * @api
 */
final readonly class PluginTag
{
    /**
     * @param class-string $type  The plugin type, an interface.
     * @param class-string $class The plugin, in the module's own namespace, with no required constructor parameter.
     */
    public function __construct(
        public string $type,
        public string $class,
    ) {
    }
}
