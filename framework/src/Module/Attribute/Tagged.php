<?php

declare(strict_types=1);

namespace AIC\Module\Attribute;

use Attribute;

/**
 * Marks a constructor parameter that takes a plugin list (#93): every plugin
 * tagged under the type, in registration order, as the container's `tagged()`
 * gives them. The build reads it and passes the list; it does nothing at run
 * time.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Tagged
{
    /**
     * @param class-string $type The plugin type.
     */
    public function __construct(
        public string $type,
    ) {
    }
}
