<?php

declare(strict_types=1);

namespace App;

use AIC\Container\Application\Container;
use AIC\Container\Contracts\ServiceProvider;
use App\Greeting\Greeter;

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
     * @return list<object> The Greeter plugins, in tag order.
     */
    public function greeters(): array
    {
        return $this->container->tagged(Greeter::class);
    }
}
