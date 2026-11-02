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
use AIC\Component\Domain\SourcedComponent;
use AIC\Component\Exception\DuplicateAnchor;
use AIC\Component\Exception\UnknownComponent;
use AIC\Component\Exception\UnmarkedComponent;

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
 * children through. A region writes its element, and each child, keyed or
 * `cN`, carries Tessera's `data-t-c="KEY"` on its root element (protocol 1,
 * #189), printed where the build put `marks()`; a template child carries its
 * own key, and an inline child none. A pass aimed at a target path renders only
 * the way to it and keeps the target's markup. It holds the path it is at, so
 * it is the one mutable class of rendering and lives for one call. A part
 * built from a source is built only when the pass renders it (#192), so an
 * aimed pass never builds the parts off its way.
 *
 * Templates run as templates (#155): a template's lookup opens the pass's
 * buffer, requires the template, and closes it; the region element and the
 * marks are framework templates too (`frame()`). `text()` is the one
 * escaping path.
 */
final class RenderPass
{
    /**
     * The path of the component being rendered, from its region: `content/c0/NoteList.1`.
     */
    private string $path = '';

    /**
     * The target's markup, its root marked, once rendered.
     */
    private string $captured = '';

    /**
     * Whether the target was rendered.
     */
    private bool $found = false;

    /**
     * The marks the next component root prints (#189).
     */
    private readonly PendingMarks $pendingMarks;

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
        $this->pendingMarks = new PendingMarks();
    }

    /**
     * @param Region $region A region.
     *
     * @throws UnknownComponent When no template renders one of its components.
     *
     * @return string Its element, each component's root marked with its own key or `cN`; when aimed, only the
     *                way to the target.
     */
    public function region(Region $region): string
    {
        $outer = $this->path;
        $this->path = $region->name();
        $markup = '';
        $position = 0;

        foreach ($region->components() as $component) {
            $key = $component instanceof Keyed
                ? $component->key()
                : 'c' . $position;
            $markup .= $this->child($component, $key);
            $position += 1;
        }

        $this->path = $outer;

        return $this->frame(new RegionFrame($region->name(), $markup));
    }

    /**
     * @param Component $component A region's or a template's child component.
     * @param string    $key       Its key among its siblings: its own key or `cN` in a region, `Name.N` or
     *                             `Name.N:id` in a template.
     *
     * @throws UnknownComponent  When no template renders it.
     * @throws UnmarkedComponent When its template has no root element for its marks.
     * @throws DuplicateAnchor   When its anchor was marked before on this page.
     *
     * @return string Its markup, its root marked with that key and, when it is Keyed, its props token and anchor
     *                wherever it renders (#194); when aimed elsewhere, nothing.
     */
    public function child(Component $component, string $key): string
    {
        $childFrame = $component instanceof Keyed
            ? new ChildFrame($key, $component->props(), $component->anchor())
            : new ChildFrame($key, '');

        return $this->marked($component, $key, $childFrame);
    }

    /**
     * Printed by the build in each component template's root start tag (#189).
     *
     * @return string The root's Tessera attributes when the pass is marking this component: `data-t-c` with its
     *                key, and `data-t-props` with its props token when it has one; empty when it renders inline.
     */
    public function marks(): string
    {
        $childFrame = $this->pendingMarks->take();

        return $childFrame instanceof ChildFrame
            ? $this->frame($childFrame)
            : '';
    }

    /**
     * @param Component $component A component written without marks: a layout's own, or an atom's.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup; a RawHtml's trusted markup as it is (#99), the one string not made by a template; a
     *                SourcedComponent's part, built from its source now (#192).
     */
    public function inline(Component $component): string
    {
        $inner = $component instanceof KeyedComponent
            ? $component->component
            : $component;

        if ($inner instanceof SourcedComponent) {
            $inner = $inner->componentSource->build();
        }

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
     * @param Frame $frame A region's element, a child's marks or a fragment answer.
     *
     * @return string It written by its framework template.
     */
    public function frame(Frame $frame): string
    {
        $this->open($frame);

        if ($frame instanceof RegionFrame) {
            require __DIR__ . '/../../../templates/tessera/region.phtml';
        } elseif ($frame instanceof ChildFrame) {
            require __DIR__ . '/../../../templates/tessera/marker.phtml';
        } elseif ($frame instanceof FragmentFrame) {
            require __DIR__ . '/../../../templates/tessera/fragments.phtml';
        }

        return $this->close();
    }

    /**
     * @return string The path being rendered now (`content/c0/NoteList.1`); a template reads it while it renders, and
     *                an atom inside a component shares that component's path. Empty outside a region (#229).
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return bool Whether the target was rendered.
     */
    public function found(): bool
    {
        return $this->found;
    }

    /**
     * @return string The target's markup, its root marked; empty until it is rendered.
     */
    public function captured(): string
    {
        return $this->captured;
    }

    /**
     * @param Component  $component  A component under the current path.
     * @param string     $key        Its key, the next segment of the path.
     * @param ChildFrame $childFrame The marks its root prints: its key, props token and anchor.
     *
     * @throws UnknownComponent  When no template renders it.
     * @throws UnmarkedComponent When its markup printed no marks: its template has no root element for them.
     * @throws DuplicateAnchor   When its anchor was marked before on this page.
     *
     * @return string Its markup, its root marked with its key; nothing when it is off the way to the target.
     */
    private function marked(Component $component, string $key, ChildFrame $childFrame): string
    {
        $outer = $this->path;
        $this->path = $outer === ''
            ? $key
            : $outer . '/' . $key;

        if (!$this->onTheWay()) {
            $this->path = $outer;

            return '';
        }

        $this->pendingMarks->set($childFrame);
        $markup = $this->inline($component);

        if ($this->pendingMarks->take() instanceof ChildFrame) {
            throw new UnmarkedComponent($this->path);
        }

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
