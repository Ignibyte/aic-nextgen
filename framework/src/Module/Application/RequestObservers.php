<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Component\Application\ComponentTemplates;
use AIC\Component\Application\ObservedTemplates;
use AIC\Component\Application\Renderer;
use AIC\Component\Contracts\RenderObserver;
use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Contracts\PublicAssets;
use AIC\HttpClient\Application\Outbound;
use AIC\HttpClient\Application\OutboundWiring;
use AIC\HttpClient\Contracts\OutboundObserver;
use AIC\HttpClient\Domain\Upstreams;
use AIC\Kernel\Contracts\RequestObserver;
use AIC\Profiler\Application\ProfilerMount;
use AIC\Routing\Contracts\RouteObserver;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * What observes a dynamic request (#229): the request itself, the router's
 * dispatch, the Renderer and the calls to upstreams (#305). None by default;
 * the composition root gives a profiled request all four.
 */
final readonly class RequestObservers
{
    /**
     * @param RequestObserver|null  $request  Told when a request starts and what it answered.
     * @param RouteObserver|null    $route    Told when the handler gets the request and answers.
     * @param RenderObserver|null   $render   Told each template's and each render's time.
     * @param bool                  $pages    Whether the profiler's pages answer `/_profiler` (#230).
     * @param OutboundObserver|null $outbound Told of each call to an upstream (#305).
     */
    public function __construct(
        public ?RequestObserver $request = null,
        public ?RouteObserver $route = null,
        public ?RenderObserver $render = null,
        public bool $pages = false,
        public ?OutboundObserver $outbound = null,
    ) {
    }

    /**
     * @param Upstreams $upstreams The upstreams config names.
     *
     * @return Outbound The outbound client and pool, observed when there is an outbound observer.
     */
    public function outbound(Upstreams $upstreams): Outbound
    {
        return (new OutboundWiring())->outbound($upstreams, $this->outbound);
    }

    /**
     * @param RequestHandlerInterface $requestHandler  The app.
     * @param Database                $database        The database the profiles are kept in.
     * @param ResponseBuilder         $responseBuilder Makes the responses.
     * @param PublicAssets            $publicAssets    The asset catalog.
     *
     * @return RequestHandlerInterface The profiler's pages in front of the app when they are on; the app otherwise.
     */
    public function around(
        RequestHandlerInterface $requestHandler,
        Database $database,
        ResponseBuilder $responseBuilder,
        PublicAssets $publicAssets,
    ): RequestHandlerInterface {
        return $this->pages
            ? (new ProfilerMount())->around($requestHandler, $database, $responseBuilder, $publicAssets)
            : $requestHandler;
    }

    /**
     * @param ComponentTemplates $componentTemplates The app's templates.
     *
     * @return Renderer The Renderer over them: timed, each template and each render told to the render observer, when
     *                  there is one; plain otherwise.
     */
    public function renderer(ComponentTemplates $componentTemplates): Renderer
    {
        return $this->render instanceof RenderObserver
            ? new Renderer(new ObservedTemplates($componentTemplates, $this->render), $this->render)
            : new Renderer($componentTemplates);
    }
}
