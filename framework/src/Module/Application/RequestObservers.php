<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Component\Application\ComponentTemplates;
use AIC\Component\Application\ObservedTemplates;
use AIC\Component\Application\Renderer;
use AIC\Component\Contracts\RenderObserver;
use AIC\Kernel\Contracts\RequestObserver;
use AIC\Routing\Contracts\RouteObserver;

/**
 * What observes a dynamic request (#229): the request itself, the router's
 * dispatch and the Renderer. None by default; the composition root gives a
 * profiled request all three.
 */
final readonly class RequestObservers
{
    /**
     * @param RequestObserver|null $request Told when a request starts and what it answered.
     * @param RouteObserver|null   $route   Told when the handler gets the request and answers.
     * @param RenderObserver|null  $render  Told each template's and each render's time.
     */
    public function __construct(
        public ?RequestObserver $request = null,
        public ?RouteObserver $route = null,
        public ?RenderObserver $render = null,
    ) {
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
