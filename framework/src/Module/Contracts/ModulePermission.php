<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * A permission a module declares (#304): its name, which a route's `needs:`
 * and a role's grant use, and what it allows. The build refuses a name two
 * modules declare.
 */
final readonly class ModulePermission implements ModuleDeclaration
{
    /**
     * @param string $name        Such as `notes.delete-pinned`.
     * @param string $description What it allows.
     */
    public function __construct(
        public string $name,
        public string $description,
    ) {
    }
}
