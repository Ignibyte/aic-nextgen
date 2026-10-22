<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Badge;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Domain\TrackedAssets;
use AIC\Component\Exception\UnknownComponent;
use Override;

/**
 * The markup of the catalog's text atoms (#98): links, badges, headings,
 * paragraphs and a layout's tracked assets, hand-written because an empty
 * attribute is left out. It ends the build's template lookup; the controls'
 * and the form atoms' templates follow it.
 */
final readonly class AtomTemplates implements ComponentTemplates
{
    use RendersMarkup;

    /**
     * @param ComponentTemplates $componentTemplates The controls' templates, then the form atoms'.
     */
    public function __construct(
        private ComponentTemplates $componentTemplates = new ControlTemplates(),
    ) {
    }

    /**
     * @param Component  $component  A component.
     * @param RenderPass $renderPass The pass rendering it.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup.
     */
    #[Override]
    public function compose(Component $component, RenderPass $renderPass): string
    {
        return match (true) {
            $component instanceof Link => $this->link($component),
            $component instanceof Badge => $this->badge($component),
            $component instanceof Heading => '<h2>' . self::text($component->text) . '</h2>',
            $component instanceof Paragraph => $this->paragraph($component),
            $component instanceof TrackedAssets => $this->trackedAssets($component),
            default => $this->componentTemplates->compose($component, $renderPass),
        };
    }

    /**
     * @param Link $link A link.
     *
     * @return string `<a class="aic-link">`.
     */
    private function link(Link $link): string
    {
        return '<a' . self::attribute('href', $link->href) . self::attribute('data-target', $link->target)
            . ($link->current ? ' aria-current="page"' : '')
            . ' class="aic-link">' . self::text($link->label) . '</a>';
    }

    /**
     * @param Badge $badge A badge.
     *
     * @return string `<span class="aic-badge">`.
     */
    private function badge(Badge $badge): string
    {
        return '<span' . self::attribute('id', $badge->id) . ' class="aic-badge">' . self::text($badge->text) . '</span>';
    }

    /**
     * @param Paragraph $paragraph A paragraph.
     *
     * @return string `<p>`.
     */
    private function paragraph(Paragraph $paragraph): string
    {
        return '<p' . self::attribute('id', $paragraph->id) . '>' . self::text($paragraph->text) . '</p>';
    }

    /**
     * @param TrackedAssets $trackedAssets A layout's tracked assets.
     *
     * @return string The stylesheet's `<link>` and the script's `<script>`, each `data-track`.
     */
    private function trackedAssets(TrackedAssets $trackedAssets): string
    {
        return '<link rel="stylesheet" href="' . self::text($trackedAssets->stylesheet) . '" data-track>'
            . '<script defer src="' . self::text($trackedAssets->script) . '" data-track></script>';
    }
}
