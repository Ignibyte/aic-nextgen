<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Database\Contracts\Database;
use AIC\Http\Application\AssetFiles;
use AIC\Http\Application\AssetResponses;
use AIC\Http\Application\FileResponses;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Application\StaticAssets;
use AIC\Kernel\Application\Kernel;
use AIC\Sapi\Contracts\RequestExtractor;
use InvalidArgumentException;

/**
 * Compose the public boundary around an inert dynamic handler. No user
 * provider, route, handler or session is assembled before StaticAssets decides
 * whether the request belongs to the compiled public catalog.
 */
final readonly class AppKernel
{
    /**
     * The composed kernel.
     */
    private Kernel $kernel;

    /**
     * @param HandlerContexts  $handlerContexts  Builds the handler context from the app's roots.
     * @param RequestExtractor $requestExtractor Turns the SAPI request into PSR-7.
     * @param ResponseBuilder  $responseBuilder  Makes the PSR-7 responses.
     * @param Database         $database         The database.
     * @param WebWiring        $webWiring        The modules' generated wiring.
     *
     * @throws InvalidArgumentException When the compiled presentation contains invalid public asset metadata.
     */
    public function __construct(
        HandlerContexts $handlerContexts,
        RequestExtractor $requestExtractor,
        ResponseBuilder $responseBuilder,
        Database $database,
        WebWiring $webWiring,
    ) {
        $webPresentation = $webWiring->presentation();
        $dynamicApplication = new DynamicApplication(
            $handlerContexts,
            $webWiring,
            new FrameworkStack($responseBuilder, $database, $handlerContexts->maxBodyBytes()),
            $webPresentation,
        );
        $staticAssets = new StaticAssets(
            $webPresentation->publicAssets,
            new AssetFiles($handlerContexts->root()),
            new AssetResponses($responseBuilder, new FileResponses($responseBuilder)),
            $dynamicApplication,
        );
        $this->kernel = new Kernel(
            $requestExtractor,
            $staticAssets,
            $responseBuilder,
        );
    }

    /**
     * @return Kernel The kernel that serves the request.
     */
    public function kernel(): Kernel
    {
        return $this->kernel;
    }
}
