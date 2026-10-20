<?php

declare(strict_types=1);

namespace App;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Blocks;
use AIC\Container\Application\Container;
use AIC\Container\Contracts\ServiceProvider;
use App\Greeting\Greeter;

use function array_filter;
use function array_values;

/**
 * The app's plugins: the container a ServiceProvider fills, frozen, read by
 * type. The worker's and the console's composition roots share it.
 */
final readonly class AppPlugins
{
    /**
     * The filled, frozen container.
     */
    private Container $container;

    /**
     * @param ServiceProvider $serviceProvider Registers the app's services and plugins.
     */
    public function __construct(ServiceProvider $serviceProvider)
    {
        $container = new Container();
        $serviceProvider->register($container);
        $container->freeze();
        $this->container = $container;
    }

    /**
     * @return Blocks The Block plugins, in tag order.
     */
    public function blocks(): Blocks
    {
        return new Blocks(array_values(array_filter(
            $this->container->tagged(Block::class),
            static fn (object $service): bool => $service instanceof Block,
        )));
    }

    /**
     * @return list<object> The Greeter plugins, in tag order.
     */
    public function greeters(): array
    {
        return $this->container->tagged(Greeter::class);
    }
}
