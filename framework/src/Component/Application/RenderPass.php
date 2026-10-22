<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Keyed;
use AIC\Component\Contracts\Region;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\RawHtml;
use AIC\Component\Exception\UnknownComponent;

use function str_starts_with;

/**
 * One rendering of a component tree (#98), what templates write their
 * children through. A region writes its element and each child between
 * Tessera's `<!--t:c KEY-->` markers (#70), a template child its own key's
 * markers, and an inline child none. A pass aimed at a target path renders only
 * the way to it and keeps the target's markup. It holds the path it is at, so
 * it is the one mutable class of rendering and lives for one call.
 */
final class RenderPass
{
    /**
     * The path of the component being rendered, from its region: `content/c0/NoteList.1`.
     */
    private string $path = '';

    /**
     * The target's markup, markers included, once rendered.
     */
    private string $captured = '';

    /**
     * Whether the target was rendered.
     */
    private bool $found = false;

    /**
     * @param ComponentTemplates $componentTemplates Each component's template.
     * @param string             $target             The path to render alone, such as `content/stock-list`; empty for all.
     */
    public function __construct(
        private readonly ComponentTemplates $componentTemplates,
        private readonly string $target,
    ) {
    }

    /**
     * @param Region $region A region.
     *
     * @throws UnknownComponent When no template renders one of its components.
     *
     * @return string Its element, each component between markers under its own key or `cN`; when aimed, only the
     *                way to the target.
     */
    public function region(Region $region): string
    {
        $outer = $this->path;
        $this->path = $region->name();
        $markup = '';
        $position = 0;

        foreach ($region->components() as $component) {
            $markup .= $this->regionChild($component, $position);
            $position += 1;
        }

        $this->path = $outer;

        return '<div data-region="' . $region->name() . '">' . $markup . '</div>';
    }

    /**
     * @param Component $component A template's child component.
     * @param string    $key       Its key among its siblings: `Name.N`, or `Name.N:id` in a loop.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup between markers under that key; when aimed elsewhere, nothing.
     */
    public function child(Component $component, string $key): string
    {
        return $this->marked($component, $key, $key);
    }

    /**
     * @param Component $component A component written without markers: a layout's own, or an atom's.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup; a RawHtml's trusted markup as it is (#99), the one string not made by a template.
     */
    public function inline(Component $component): string
    {
        $inner = $component instanceof KeyedComponent
            ? $component->component
            : $component;

        if ($inner instanceof RawHtml) {
            return $inner->trustedMarkup;
        }

        return $this->componentTemplates->compose($inner, $this);
    }

    /**
     * @return bool Whether the target was rendered.
     */
    public function found(): bool
    {
        return $this->found;
    }

    /**
     * @return string The target's markup, markers included; empty until it is rendered.
     */
    public function captured(): string
    {
        return $this->captured;
    }

    /**
     * @param Component $component A region's component.
     * @param int       $position  Its place in the region, from 0.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup between markers under its own key and props token, else `cN`.
     */
    private function regionChild(Component $component, int $position): string
    {
        if (!$component instanceof Keyed) {
            return $this->marked($component, 'c' . $position, 'c' . $position);
        }

        $key = $component->key();

        return $this->marked($component, $key, $component->props() === '' ? $key : $key . ' ' . $component->props());
    }

    /**
     * @param Component $component A component under the current path.
     * @param string    $key       Its key, the next segment of the path.
     * @param string    $opening   What its opening marker holds: the key, then its props token when it has one.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup between markers; nothing when it is off the way to the target.
     */
    private function marked(Component $component, string $key, string $opening): string
    {
        $outer = $this->path;
        $this->path = $outer === ''
            ? $key
            : $outer . '/' . $key;

        if (!$this->onTheWay()) {
            $this->path = $outer;

            return '';
        }

        $markup = '<!--t:c ' . $opening . '-->' . $this->inline($component) . '<!--/t:c-->';

        if ($this->target !== '' && $this->path === $this->target) {
            $this->captured = $markup;
            $this->found = true;
        }

        $this->path = $outer;

        return $markup;
    }

    /**
     * @return bool Whether the current path is the target or on the way to it; always when not aimed.
     */
    private function onTheWay(): bool
    {
        return $this->target === '' || $this->path === $this->target || str_starts_with($this->target, $this->path . '/');
    }
}
