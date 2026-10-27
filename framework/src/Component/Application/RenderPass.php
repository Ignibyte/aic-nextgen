<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Frame;
use AIC\Component\Contracts\Keyed;
use AIC\Component\Contracts\Region;
use AIC\Component\Domain\ChildFrame;
use AIC\Component\Domain\FragmentFrame;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\RawHtml;
use AIC\Component\Domain\RegionFrame;
use AIC\Component\Exception\UnknownComponent;

use function array_pop;
use function htmlspecialchars;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;
use function str_starts_with;

use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * One rendering of a component tree (#98), what templates write their
 * children through. A region writes its element and each child between
 * Tessera's `<!--t:c KEY-->` markers (#70), a template child its own key's
 * markers, and an inline child none. A pass aimed at a target path renders only
 * the way to it and keeps the target's markup. It holds the path it is at, so
 * it is the one mutable class of rendering and lives for one call.
 *
 * Templates run as templates (#155): a template's lookup opens the pass's
 * buffer, requires the template, and closes it; the region element and the
 * markers are framework templates too (`frame()`). `text()` is the one
 * escaping path.
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
     * What each output buffer this pass opened and has not closed is rendering, innermost last.
     *
     * @var list<string>
     */
    private array $rendering = [];

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

        return $this->frame(new RegionFrame($region->name(), $markup));
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
     * @param string $text Text.
     *
     * @return string It, escaped for an element's content or an attribute value: the one escaping path of templates.
     */
    public function text(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Opens an output buffer for one template, before its lookup requires it.
     *
     * @param Component|Frame $rendered What the template renders; `close()` names it.
     */
    public function open(Component|Frame $rendered): void
    {
        ob_start();
        $this->rendering[] = $rendered::class;
    }

    /**
     * @return string What the template wrote since its `open()`; the buffer is closed. Each lookup pairs one `open()`
     *                with one `close()` around one template.
     */
    public function close(): string
    {
        array_pop($this->rendering);

        return (string) ob_get_clean();
    }

    /**
     * Ends every buffer this pass opened and did not close, so a throw inside a template leaves none behind.
     */
    public function discard(): void
    {
        while (array_pop($this->rendering) !== null) {
            ob_end_clean();
        }
    }

    /**
     * @param Frame $frame A region's element, a child's markers or a fragment answer.
     *
     * @return string It written by its framework template.
     */
    public function frame(Frame $frame): string
    {
        $this->open($frame);

        if ($frame instanceof RegionFrame) {
            require __DIR__ . '/../../../templates/tessera/region.phtml';
        } elseif ($frame instanceof ChildFrame) {
            require __DIR__ . '/../../../templates/tessera/child.phtml';
        } elseif ($frame instanceof FragmentFrame) {
            require __DIR__ . '/../../../templates/tessera/fragments.phtml';
        }

        return $this->close();
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

        $markup = $this->frame(new ChildFrame($opening, $this->inline($component)));

        if ($this->target !== '' && $this->path === $this->target) {
            $this->captured = $markup;
            $this->found = true;
        }

        $this->path = $outer;

        return $markup;
    }

    /**
     * @return bool Whether the current path is the target, on the way to it or inside it; always when not aimed.
     *              The target renders whole, its descendants included, so a fragment holds the same markup as
     *              the whole document (#179).
     */
    private function onTheWay(): bool
    {
        return $this->target === ''
            || $this->path === $this->target
            || str_starts_with($this->target, $this->path . '/')
            || str_starts_with($this->path, $this->target . '/');
    }
}
