<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Exception\UnknownComponent;

/**
 * Finds a component's template and writes its markup (#98): the build's
 * generated lookup for the app's components, ending at the framework's atom
 * templates. A template writes its child components through the pass.
 */
interface ComponentTemplates
{
    /**
     * @param Component  $component  A component.
     * @param RenderPass $renderPass The pass rendering it, for its children.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup.
     */
    public function compose(Component $component, RenderPass $renderPass): string;
}
