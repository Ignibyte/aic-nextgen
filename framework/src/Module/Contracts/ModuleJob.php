<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * A queue job a module declares (#93): the name a queued message names and the
 * Job class, autowired from the console's roots at build time.
 *
 * Manifests construct it; nothing in the framework does, so it is API for them.
 *
 * @api
 */
final readonly class ModuleJob implements ModuleDeclaration
{
    /**
     * @param string       $name The job's name (`demo`).
     * @param class-string $job  The Job class, in the module's own namespace.
     */
    public function __construct(
        public string $name,
        public string $job,
    ) {
    }
}
