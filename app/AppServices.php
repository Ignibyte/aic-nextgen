<?php

declare(strict_types=1);

namespace App;

use AIC\Config\Domain\AppConfig;
use AIC\Container\Application\Container;
use AIC\Container\Contracts\ServiceProvider;
use AIC\Frankenphp\Contracts\RequestExtractor;
use AIC\Http\Application\ResponseBuilder;
use AIC\Kernel\Application\Kernel;
use AIC\Middleware\Application\MiddlewareStep;
use AIC\Routing\Application\RouteErrorMiddleware;
use AIC\Routing\Application\Router;
use AIC\Routing\Domain\RouteTable;
use App\Greeting\Greeter;
use App\Http\AppHandlers;

/**
 * The app's composition root: its plugins in the container (AppProvider), its
 * handlers by route id, the router, the middleware stack and the kernel, built
 * once per worker. The worker builds the
 * framework's SAPI services and hands their pieces in.
 */
final readonly class AppServices
{
    /**
     * Answers each request.
     */
    private Kernel $kernel;

    /**
     * @param AppConfig        $appConfig        The app's settings.
     * @param RouteTable       $routeTable       The routes, from config/routes.php.
     * @param RequestExtractor $requestExtractor Makes the PSR-7 request from a SAPI request.
     * @param ResponseBuilder  $responseBuilder  Makes responses.
     * @param string           $bootedAt         When this worker booted, ISO 8601 in UTC.
     * @param string           $resources        The app's resources directory.
     */
    public function __construct(
        AppConfig $appConfig,
        RouteTable $routeTable,
        RequestExtractor $requestExtractor,
        ResponseBuilder $responseBuilder,
        string $bootedAt,
        string $resources,
    ) {
        $container = self::container(new AppProvider());
        $appHandlers = new AppHandlers($appConfig, $responseBuilder, $bootedAt, $resources, $container->tagged(Greeter::class));
        $router = new Router($routeTable, $appHandlers->byId());
        $this->kernel = new Kernel(
            $requestExtractor,
            new MiddlewareStep(new RouteErrorMiddleware($responseBuilder), $router),
            $responseBuilder,
        );
    }

    /**
     * @return Kernel Answers each request.
     */
    public function kernel(): Kernel
    {
        return $this->kernel;
    }

    /**
     * @param ServiceProvider $serviceProvider Registers the app's services and plugins.
     *
     * @return Container The container, filled and frozen.
     */
    private static function container(ServiceProvider $serviceProvider): Container
    {
        $container = new Container();
        $serviceProvider->register($container);
        $container->freeze();

        return $container;
    }
}
