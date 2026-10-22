<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Container\Contracts\ServiceProvider;

/**
 * The modules' blocks and plugins (#93), for both kernels' plugins: a service
 * provider the generated wiring is when some module has a block or a plugin.
 */
interface PluginWiring extends ServiceProvider
{
}
