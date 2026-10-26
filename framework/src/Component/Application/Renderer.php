<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Layout;
use AIC\Component\Exception\UnknownComponent;
use InvalidArgumentException;

use function count;
use function explode;
use function htmlspecialchars;
use function in_array;

/**
 * Turns a page's layout, regions, components and blocks into HTML (#98, #87),
 * the only code that makes an `Html`. A document is the layout's template; a
 * Tessera fragment answer is the title, the token and the tracked assets, then
 * each target rendered from the same tree by its path (protocol 5.1), so a
 * fragment is never a second way to make markup.
 */
final readonly class Renderer
{
    /**
     * @param ComponentTemplates $componentTemplates Each component's template.
     */
    public function __construct(
        private ComponentTemplates $componentTemplates,
    ) {
    }

    /**
     * @param Component $component One component, such as a stream's element patch (#115).
     *
     * @throws UnknownComponent When no template renders it or one of its children.
     *
     * @return Html Its markup inline: no region and no marker around it; its children keep theirs.
     */
    public function elements(Component $component): Html
    {
        return new Html((new RenderPass($this->componentTemplates, ''))->inline($component));
    }

    /**
     * @param Layout $layout The page.
     *
     * @throws UnknownComponent When no template renders one of its components.
     *
     * @return Html The whole document.
     */
    public function document(Layout $layout): Html
    {
        return new Html((new RenderPass($this->componentTemplates, ''))->inline($layout));
    }

    /**
     * @param Layout       $layout  The page.
     * @param list<string> $targets The regions and component paths Tessera asked for, in order; at least one.
     *
     * @throws UnknownComponent When no template renders one of its components.
     *
     * @return Html|null The title, the `tessera-csrf-token` meta, the tracked assets and one `<template>` per target;
     *                   null when the page has no such target, and the whole document answers.
     */
    public function fragments(Layout $layout, array $targets): ?Html
    {
        $templates = '';

        foreach ($targets as $target) {
            $markup = $this->fragment($layout, $target);

            if ($markup === '') {
                return null;
            }

            $templates .= '<template data-target="' . $target . '">' . $markup . '</template>';
        }

        $csrfToken = $layout->csrfToken();
        $csrfMeta = $csrfToken === ''
            ? ''
            : '<meta name="tessera-csrf-token" content="' . htmlspecialchars($csrfToken) . '">';
        $assets = (new RenderPass($this->componentTemplates, ''))->inline($layout->assets());

        return new Html('<title>' . htmlspecialchars($layout->title()) . '</title>' . $csrfMeta . $assets . $templates);
    }

    /**
     * @param Layout $layout The page.
     * @param string $target A region, or a component's path below one.
     *
     * @throws UnknownComponent         When no template renders one of its components.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return string The target's markup as the document renders it; empty when the page has no such target.
     */
    private function fragment(Layout $layout, string $target): string
    {
        $segments = explode('/', $target);

        if (!in_array($segments[0], $layout->names(), true)) {
            return '';
        }

        $region = $layout->regions()->region($segments[0]);

        if (count($segments) === 1) {
            return (new RenderPass($this->componentTemplates, ''))->region($region);
        }

        $renderPass = new RenderPass($this->componentTemplates, $target);
        $renderPass->region($region);

        return $renderPass->found()
            ? $renderPass->captured()
            : '';
    }
}
