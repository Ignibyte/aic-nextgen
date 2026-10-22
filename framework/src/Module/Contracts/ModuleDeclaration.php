<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * One thing a manifest declares (#93): a route, a binding, a plugin, a
 * command, a job, a schedule, a middleware, or a bare name by its section.
 * The build reads a manifest's declarations by their class.
 *
 * @api
 */
interface ModuleDeclaration
{
}
