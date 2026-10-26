<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\AssetInput;
use Gates\Domain\TemplateBinding;
use Gates\Domain\ThemeBinding;
use Gates\Domain\ThemeLayoutBinding;
use InvalidArgumentException;

use function array_map;

/**
 * The surface's `theme` object (#153): the configured theme's name, layouts, page data and regions, each
 * component with its template, and each asset with its role. Null when the app names no theme.
 */
final readonly class SurfaceTheme
{
    /**
     * @param string            $root         The project folder.
     * @param ThemeBinding|null $themeBinding The theme the build planned; null when the app names none.
     */
    public function __construct(private string $root = '', private ?ThemeBinding $themeBinding = null)
    {
    }

    /**
     * @throws InvalidArgumentException When an asset cannot be read.
     *
     * @return array<string, list<array<string, list<string>|string>>|list<string>|string>|null The theme, keys in order.
     */
    public function row(): ?array
    {
        $theme = $this->themeBinding;

        if ($theme === null) {
            return null;
        }

        return [
            'assets' => array_map(
                static fn (AssetInput $assetInput): array => ['kind' => $assetInput->assetKind->name, 'path' => $assetInput->source],
                (new ThemeAssetInputs($this->root))->read($theme),
            ),
            'components' => array_map($this->component(...), $theme->components),
            'html' => $theme->html->source,
            'layouts' => array_map($this->layout(...), $theme->variants),
            'name' => $theme->name,
            'page' => $theme->page->source,
            'pageData' => $theme->page->component,
            'regions' => $theme->regions,
        ];
    }

    /**
     * @param ThemeLayoutBinding $themeLayoutBinding A named layout.
     *
     * @return array<string, list<string>|string> Its name, identity, page data and regions, and the files it ships
     *                                            (empty for a file that falls back to the default).
     */
    private function layout(ThemeLayoutBinding $themeLayoutBinding): array
    {
        return [
            'html' => $themeLayoutBinding->html->source ?? '',
            'identity' => $themeLayoutBinding->identity,
            'name' => $themeLayoutBinding->name,
            'page' => $themeLayoutBinding->page->source ?? '',
            'pageData' => $themeLayoutBinding->pageData,
            'regions' => $themeLayoutBinding->regions,
        ];
    }

    /**
     * @param TemplateBinding $templateBinding One component's template.
     *
     * @return array<string, string> The component, its source and its generated class.
     */
    private function component(TemplateBinding $templateBinding): array
    {
        return [
            'component' => $templateBinding->component,
            'generated' => $templateBinding->generated,
            'source' => $templateBinding->source,
        ];
    }
}
