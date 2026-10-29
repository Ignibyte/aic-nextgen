<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Container\Application\Container;
use AIC\Container\Exception\ContainerException;

/**
 * The modules' plugins, as both kernels hold them (#93): a container this
 * builds itself, registers the wiring's plugins in and freezes, so no
 * container is handed around; callers ask it for the plugins of a type.
 * Blocks are wired like controllers since #203.
 *
 * @api
 */
final readonly class Plugins
{
    /**
     * The frozen container holding the plugins.
     */
    private Container $container;

    /**
     * @param PluginWiring|null $pluginWiring The modules' plugins, when some module has any.
     */
    public function __construct(?PluginWiring $pluginWiring)
    {
        $container = new Container();

        if ($pluginWiring instanceof PluginWiring) {
            $pluginWiring->register($container);
        }

        $container->freeze();
        $this->container = $container;
    }

    /**
     * @param string $type A plugin type.
     *
     * @throws ContainerException When a registered service cannot be resolved.
     *
     * @return list<object> The plugins tagged under it, in registration order.
     */
    public function tagged(string $type): array
    {
        return $this->container->tagged($type);
    }
}
