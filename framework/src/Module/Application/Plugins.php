<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Blocks;
use AIC\Container\Application\Container;
use AIC\Container\Exception\ContainerException;

use function array_filter;
use function array_values;

/**
 * The modules' blocks and plugins, as both kernels hold them (#93): a
 * container this builds itself, registers the wiring's plugins in and
 * freezes, so no container is handed around; callers ask it for the plugins
 * of a type, or for the blocks.
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

    /**
     * @throws ContainerException When a registered service cannot be resolved.
     *
     * @return Blocks The blocks among the plugins, in registration order.
     */
    public function blocks(): Blocks
    {
        return new Blocks(array_values(array_filter(
            $this->container->tagged(Block::class),
            static fn (object $plugin): bool => $plugin instanceof Block,
        )));
    }
}
