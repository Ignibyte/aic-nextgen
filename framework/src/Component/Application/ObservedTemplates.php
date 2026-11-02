<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\RenderObserver;
use AIC\Component\Exception\UnknownComponent;
use AIC\Kernel\Domain\Monotonic;
use Override;

/**
 * Each component's template, timed (#229): it composes through the app's
 * templates and tells the observer the path, the class and the time of every
 * template it ran, atoms included. Only a profiled request's Renderer takes it.
 */
final readonly class ObservedTemplates implements ComponentTemplates
{
    /**
     * @param ComponentTemplates $componentTemplates The templates that render.
     * @param RenderObserver     $renderObserver     Told each template's path, class and time.
     * @param Monotonic          $monotonic          The clock the times are read from.
     */
    public function __construct(
        private ComponentTemplates $componentTemplates,
        private RenderObserver $renderObserver,
        private Monotonic $monotonic = new Monotonic(),
    ) {
    }

    /**
     * @param Component  $component  The component to render.
     * @param RenderPass $renderPass The pass it renders in.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup, as the templates wrote it.
     */
    #[Override]
    public function compose(Component $component, RenderPass $renderPass): string
    {
        $started = $this->monotonic->microseconds();
        $markup = $this->componentTemplates->compose($component, $renderPass);
        $this->renderObserver->rendered($renderPass->path(), $component::class, $this->monotonic->microseconds() - $started);

        return $markup;
    }
}
