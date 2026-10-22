<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One module with everything the build wired for it (#93): its controllers in
 * route order, its middleware in declaration order, and its commands and jobs
 * by name.
 */
final readonly class WiredModule
{
    /**
     * @param ModuleDefinition           $module      The module.
     * @param list<WiredObject>          $controllers Its route controllers, in route order.
     * @param list<WiredObject>          $middleware  Its middleware, in declaration order.
     * @param array<string, WiredObject> $commands    Its commands by name.
     * @param array<string, WiredObject> $jobs        Its jobs by name.
     */
    public function __construct(
        public ModuleDefinition $module,
        public array $controllers,
        public array $middleware,
        public array $commands,
        public array $jobs,
    ) {
    }
}
