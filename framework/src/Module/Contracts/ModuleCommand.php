<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * A console command a module declares (#93): the name `bin/aic` runs it by and
 * the Command class, autowired from the console's roots at build time.
 *
 * Manifests construct it; nothing in the framework does, so it is API for them.
 *
 * @api
 */
final readonly class ModuleCommand implements ModuleDeclaration
{
    /**
     * @param string       $name    The command's name (`user:add`).
     * @param class-string $command The Command class, in the module's own namespace.
     */
    public function __construct(
        public string $name,
        public string $command,
    ) {
    }
}
