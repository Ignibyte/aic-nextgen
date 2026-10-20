<?php

declare(strict_types=1);

namespace App;

use AIC\Config\Domain\AppConfig;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Http\Application\ResponseBuilder;
use AIC\Kernel\Application\Kernel;
use AIC\Routing\Application\Router;
use AIC\Routing\Domain\RouteTable;
use AIC\Sapi\Contracts\RequestExtractor;
use AIC\Secret\Application\HmacSigner;
use AIC\Secret\Domain\AppSecret;
use AIC\Session\Domain\StringMapJson;
use App\Http\AppHandlers;

/**
 * The app's composition root: its plugins (AppPlugins over AppProvider), its
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
     * @param string           $bootedAt         When the app booted to serve this request, ISO 8601 in UTC.
     * @param string           $root             The app's root directory.
     * @param Database         $database         The database.
     * @param AppSecret        $appSecret        The app secret, from the environment.
     */
    public function __construct(
        AppConfig $appConfig,
        RouteTable $routeTable,
        RequestExtractor $requestExtractor,
        ResponseBuilder $responseBuilder,
        string $bootedAt,
        string $root,
        Database $database,
        AppSecret $appSecret,
    ) {
        $appPlugins = new AppPlugins(new AppProvider());
        $appHandlers = new AppHandlers(
            $appConfig,
            $responseBuilder,
            $bootedAt,
            $root,
            $appPlugins->greeters(),
            $database,
            $appPlugins->blocks(),
            new EnvelopeCodec(new HmacSigner($appSecret), new StringMapJson()),
        );
        $router = new Router($routeTable, $appHandlers->byId());
        $this->kernel = new Kernel(
            $requestExtractor,
            (new AppMiddleware($responseBuilder, $database))->around($router),
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
}
