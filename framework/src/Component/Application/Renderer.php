<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\RenderObserver;
use AIC\Component\Domain\FragmentFrame;
use AIC\Component\Exception\UnknownComponent;
use AIC\Kernel\Domain\Monotonic;
use InvalidArgumentException;

use function count;
use function explode;
use function in_array;
use function strrpos;
use function substr;

/**
 * Turns a page's layout, regions, components and blocks into HTML (#98, #87),
 * the only code that makes an `Html`. A document is the layout's template; a
 * Tessera fragment answer is the title, the token and the tracked assets, then
 * each target rendered from the same tree by its path (protocol 5.1), so a
 * fragment is never a second way to make markup. Every pass discards its open
 * buffers when a template throws, so an error leaves no partial markup (#155).
 * Its observer, when it has one, is told each whole render's time (#229).
 */
final readonly class Renderer
{
    /**
     * @param ComponentTemplates  $componentTemplates Each component's template.
     * @param RenderObserver|null $renderObserver     Told how long each whole render took; none by default.
     * @param Monotonic           $monotonic          The clock the times are read from.
     */
    public function __construct(
        private ComponentTemplates $componentTemplates,
        private ?RenderObserver $renderObserver = null,
        private Monotonic $monotonic = new Monotonic(),
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
        return $this->whole($component);
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
        return $this->whole($layout, true);
    }

    /**
     * @param Layout                                                        $layout        The page.
     * @param list<string>                                                  $targets       The regions and component
     *                                                                                     paths Tessera asked for, in
     *                                                                                     order; at least one.
     * @param bool                                                          $removing      Whether a component path the
     *                                                                                     page no longer renders, below
     *                                                                                     a region it places, is
     *                                                                                     answered removed (#247).
     * @param list<array{scope: string, json: string, onlyIfMissing: bool}> $signalPatches The page's signal patches, in
     *                                                                                     order, each JSON safe in a
     *                                                                                     script (#253).
     *
     * @throws UnknownComponent When no template renders one of its components.
     *
     * @return Html|null The title, the `tessera-csrf-token` meta, the tracked assets, one `<template>` per target and
     *                   each signal patch, written by the framework's fragment template; null when the page has no such
     *                   target and it is not removed, and the whole document answers.
     */
    public function fragments(Layout $layout, array $targets, bool $removing = false, array $signalPatches = []): ?Html
    {
        $started = $this->monotonic->microseconds();
        $templates = [];

        foreach ($targets as $target) {
            $template = $this->template($layout, $target, $removing);

            if ($template === null) {
                $this->passed($started);

                return null;
            }

            $templates[] = $template;
        }

        $renderPass = $this->pass('');

        try {
            $assets = $renderPass->inline($layout->assets());

            $fragmentFrame = new FragmentFrame($layout->title(), $layout->csrfToken(), $assets, $templates, $signalPatches);

            return new Html($renderPass->frame($fragmentFrame));
        } finally {
            $renderPass->discard();
            $this->passed($started);
        }
    }

    /**
     * @param Layout $layout   The page.
     * @param string $target   A region, or a component's path below one.
     * @param bool   $removing Whether a component path the page no longer renders is answered removed.
     *
     * @throws UnknownComponent         When no template renders one of its components.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return array{target: string, markup: string, mode: string}|null The target's template, its mode empty for
     *                                                                  Tessera's default morph; null when it is gone
     *                                                                  and not removed.
     */
    private function template(Layout $layout, string $target, bool $removing): ?array
    {
        $markup = $this->fragment($layout, $target);

        if ($markup !== '') {
            return ['target' => $target, 'markup' => $markup, 'mode' => ''];
        }

        $segments = explode('/', $target);

        return $removing && count($segments) > 1 && in_array($segments[0], $layout->names(), true)
            ? ['target' => $target, 'markup' => '', 'mode' => 'remove']
            : null;
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

    /**
     * @param Component $component A layout or one component, rendered inline in one timed pass.
     * @param bool      $document  Whether it is a whole document, which the observer may add to (#231).
     *
     * @throws UnknownComponent When no template renders it or one of its children.
     *
     * @return Html Its markup.
     */
    private function whole(Component $component, bool $document = false): Html
    {
        $started = $this->monotonic->microseconds();
        $renderPass = $this->pass('');

        try {
            $markup = $renderPass->inline($component);

            return new Html($document ? $this->added($markup, $renderPass) : $markup);
        } finally {
            $renderPass->discard();
            $this->passed($started);
        }
    }

    /**
     * @param string     $markup     A whole document.
     * @param RenderPass $renderPass The pass it was rendered in.
     *
     * @throws UnknownComponent When no template renders an addition.
     *
     * @return string It with the observer's additions before `</head>` and `</body>` (#231); as it was without an
     *                observer, an addition, or the two closing tags in order.
     */
    private function added(string $markup, RenderPass $renderPass): string
    {
        $renderObserver = $this->renderObserver;
        $headEnd = strrpos($markup, '</head>');
        $bodyEnd = strrpos($markup, '</body>');

        if (
            !$renderObserver instanceof RenderObserver
            || $headEnd === false
            || $bodyEnd === false
            || $bodyEnd < $headEnd
        ) {
            return $markup;
        }

        $head = $renderObserver->headEnd();
        $body = $renderObserver->bodyEnd();

        return substr($markup, 0, $headEnd)
            . ($head instanceof Component ? $renderPass->inline($head) : '')
            . substr($markup, $headEnd, $bodyEnd - $headEnd)
            . ($body instanceof Component ? $renderPass->inline($body) : '')
            . substr($markup, $bodyEnd);
    }

    /**
     * @param int $started When the render began, by the monotonic clock.
     */
    private function passed(int $started): void
    {
        if (!($this->renderObserver instanceof RenderObserver)) {
            return;
        }

        $this->renderObserver->passed($this->monotonic->microseconds() - $started);
    }
}
