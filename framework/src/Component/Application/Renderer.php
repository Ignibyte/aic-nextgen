<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Layout;
use AIC\Component\Domain\FragmentFrame;
use AIC\Component\Exception\UnknownComponent;
use InvalidArgumentException;

use function count;
use function explode;
use function in_array;

/**
 * Turns a page's layout, regions, components and blocks into HTML (#98, #87),
 * the only code that makes an `Html`. A document is the layout's template; a
 * Tessera fragment answer is the title, the token and the tracked assets, then
 * each target rendered from the same tree by its path (protocol 5.1), so a
 * fragment is never a second way to make markup. Every pass discards its open
 * buffers when a template throws, so an error leaves no partial markup (#155).
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
        $renderPass = $this->pass('');

        try {
            return new Html($renderPass->inline($component));
        } finally {
            $renderPass->discard();
        }
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
        $renderPass = $this->pass('');

        try {
            return new Html($renderPass->inline($layout));
        } finally {
            $renderPass->discard();
        }
    }

    /**
     * @param Layout       $layout  The page.
     * @param list<string> $targets The regions and component paths Tessera asked for, in order; at least one.
     *
     * @throws UnknownComponent When no template renders one of its components.
     *
     * @return Html|null The title, the `tessera-csrf-token` meta, the tracked assets and one `<template>` per target,
     *                   written by the framework's fragment template; null when the page has no such target, and the
     *                   whole document answers.
     */
    public function fragments(Layout $layout, array $targets): ?Html
    {
        $templates = [];

        foreach ($targets as $target) {
            $markup = $this->fragment($layout, $target);

            if ($markup === '') {
                return null;
            }

            $templates[] = ['target' => $target, 'markup' => $markup];
        }

        $renderPass = $this->pass('');

        try {
            $assets = $renderPass->inline($layout->assets());

            return new Html($renderPass->frame(new FragmentFrame($layout->title(), $layout->csrfToken(), $assets, $templates)));
        } finally {
            $renderPass->discard();
        }
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
        $renderPass = $this->pass(count($segments) === 1 ? '' : $target);

        try {
            $markup = $renderPass->region($region);
        } finally {
            $renderPass->discard();
        }

        if (count($segments) === 1) {
            return $markup;
        }

        return $renderPass->found()
            ? $renderPass->captured()
            : '';
    }

    /**
     * @param string $target The path to render alone; empty for all.
     *
     * @return RenderPass A fresh pass over the component templates.
     */
    private function pass(string $target): RenderPass
    {
        return new RenderPass($this->componentTemplates, $target);
    }
}
