<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * A middleware a module adds to the framework's stack (#93), at its position,
 * autowired at build time. It may be the module's own class or the framework's
 * (the sign-in guard).
 *
 * Manifests construct it; nothing in the framework does, so it is API for them.
 *
 * @api
 */
final readonly class ModuleMiddleware implements ModuleDeclaration
{
    /**
     * @param class-string       $middleware A PSR-15 middleware class.
     * @param MiddlewarePosition $position   Where it runs.
     */
    public function __construct(
        public string $middleware,
        public MiddlewarePosition $position,
    ) {
    }
}
