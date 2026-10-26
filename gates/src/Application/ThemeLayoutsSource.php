<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\RenderPass;
use AIC\Component\Contracts\LayoutName;
use AIC\Component\Contracts\PageLayouts;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\ThemeLayout;
use AIC\Component\Domain\ThemePage;
use AIC\Component\Domain\TrackedAssets;
use AIC\Component\Exception\UnknownComponent;
use AIC\Http\Contracts\PublicAsset;
use AIC\Http\Contracts\PublicAssets;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;
use Override;

use function array_map;
use function implode;
use function strtr;
use function var_export;

/**
 * The theme's layout lookups (#154): the `ThemeLayoutName` enum, `ThemeLayouts::create()` choosing each named
 * layout's identity and regions, and the html adapter choosing its document template (the page adapter is
 * ThemePageSource's). Each is a `match` whose
 * `default` arm is the default layout, which is also the fallback for a file a named layout does not ship. They hold
 * types and lookups, never markup.
 */
final readonly class ThemeLayoutsSource
{
    /** The document and tracked-resource factory. */
    private const string LAYOUTS = <<<'PHP'
        final readonly class ThemeLayouts implements PageLayouts
        {
            /** @param PublicAssets $publicAssets The same compiled catalog used for public delivery. */
            public function __construct(private PublicAssets $publicAssets)
            {
            }

            /**
             * @param string          $title      The document title.
             * @param Regions         $regions    The filled regions shared with fragment rendering.
             * @param LayoutName|null $layoutName The layout a controller named; null for the default.
             *
             * @throws InvalidArgumentException When required public resources are absent.
             *
             * @return ThemeLayout The layout's document over those same regions.
             */
            #[Override]
            public function create(string $title, Regions $regions, ?LayoutName $layoutName = null): ThemeLayout
            {
                return match ($layoutName) {
        {arms}            default => $this->layout(
                        new ThemePage($regions),
                        $title,
                        {identity},
                        [{regions}],
                    ),
                };
            }

            /**
             * @param ThemePage    $themePage The page's regions and its layout's key.
             * @param string       $title     The document title.
             * @param string       $identity  The layout's Tessera identity.
             * @param list<string> $names     The layout's region names.
             *
             * @throws InvalidArgumentException When required public resources are absent.
             *
             * @return ThemeLayout The document.
             */
            private function layout(ThemePage $themePage, string $title, string $identity, array $names): ThemeLayout
            {
                return new ThemeLayout($themePage, $title, $this->trackedAssets(), $identity, $names);
            }

            /**
             * @throws InvalidArgumentException When the compiled catalog lacks a required resource.
             *
             * @return TrackedAssets The canonical resources shared by full and fragment responses.
             */
            private function trackedAssets(): TrackedAssets
            {
                return new TrackedAssets(
                    $this->assetPath($this->publicAssets->named('app/styles'), 'application stylesheet'),
                    $this->assetPath($this->publicAssets->byPath({scriptPath}), 'pinned Tessera script'),
                );
            }

            /**
             * @param PublicAsset|null $publicAsset The exact catalog match.
             * @param string           $description The required resource for a configuration refusal.
             *
             * @throws InvalidArgumentException When the catalog lacks the resource.
             *
             * @return string Its canonical public URL.
             */
            private function assetPath(?PublicAsset $publicAsset, string $description): string
            {
                if ($publicAsset === null) {
                    throw new InvalidArgumentException('The public asset catalog has no ' . $description . '.');
                }

                return $publicAsset->path;
            }
        }
        PHP;

    /** The html adapter: renders the layout's document template. */
    private const string HTML = <<<'PHP'
        final readonly class ThemeHtmlTemplate
        {
            /**
             * @param ThemeLayout $themeLayout The document.
             * @param RenderPass  $renderPass  The pass that writes its children.
             *
             * @throws UnknownComponent When a child has no registered template.
             *
             * @return string The layout's document markup; html.tpl.php's when it ships no html of its own.
             */
            public function render(ThemeLayout $themeLayout, RenderPass $renderPass): string
            {
                return match ($themeLayout->page->layout) {
        {arms}            default => (new {template}())->render($themeLayout, $renderPass),
                };
            }
        }
        PHP;

    /** @param PhpClassFile $phpClassFile Emits checked generated source. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
    }

    /**
     * @param ThemeBinding $themeBinding The theme.
     * @param string       $scriptPath   The pinned Tessera script's alias.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string `ThemeLayouts`, choosing each named layout's identity and regions.
     */
    public function layouts(ThemeBinding $themeBinding, string $scriptPath): string
    {
        $arms = '';

        foreach ($themeBinding->variants as $variant) {
            $arms .= '            ThemeLayoutName::' . $variant->case . " => \$this->layout(\n"
                . '                new ThemePage($regions, ' . var_export($variant->name, true) . "),\n"
                . "                \$title,\n"
                . '                ' . var_export($variant->identity, true) . ",\n"
                . '                [' . $this->literals($variant->regions) . "],\n"
                . "            ),\n";
        }

        $source = strtr(self::LAYOUTS, [
            '{arms}' => $arms,
            '{identity}' => var_export($themeBinding->name, true),
            '{regions}' => $this->literals($themeBinding->regions),
            '{scriptPath}' => var_export($scriptPath, true),
        ]);
        $classes = [PageLayouts::class, LayoutName::class, Regions::class, ThemeLayout::class, ThemePage::class,
            PublicAssets::class, PublicAsset::class, TrackedAssets::class, InvalidArgumentException::class, Override::class];

        return $this->phpClassFile->file('App\\Generated', $classes, [], $this->generated('the document factory.'), $source);
    }

    /**
     * @param ThemeBinding $themeBinding The theme.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string `ThemeHtmlTemplate`, rendering each layout's document or `html.tpl.php`.
     */
    public function htmlTemplate(ThemeBinding $themeBinding): string
    {
        $arms = '';
        $classes = [ThemeLayout::class, RenderPass::class, UnknownComponent::class, $themeBinding->html->templateClass];

        foreach ($themeBinding->variants as $variant) {
            $html = $variant->html;

            if ($html === null) {
                continue;
            }

            $arms .= '            ' . var_export($variant->name, true) . ' => (new '
                . $this->phpClassFile->short($html->templateClass) . "())->render(\$themeLayout, \$renderPass),\n";
            $classes[] = $html->templateClass;
        }

        $source = strtr(self::HTML, ['{arms}' => $arms, '{template}' => $this->phpClassFile->short($themeBinding->html->templateClass)]);

        return $this->phpClassFile->file('App\\Generated', $classes, [], $this->generated('the document adapter.'), $source);
    }

    /**
     * @param ThemeBinding $themeBinding The theme.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string|null `ThemeLayoutName`, a case per named layout; null when the theme ships none.
     */
    public function layoutNames(ThemeBinding $themeBinding): ?string
    {
        if ($themeBinding->variants === []) {
            return null;
        }

        $cases = '';

        foreach ($themeBinding->variants as $variant) {
            $cases .= '    case ' . $variant->case . ' = ' . var_export($variant->name, true) . ";\n";
        }

        return $this->phpClassFile->file(
            'App\\Generated',
            [LayoutName::class],
            [],
            $this->generated('the layout names a controller may pick.'),
            "enum ThemeLayoutName: string implements LayoutName\n{\n" . $cases . '}',
        );
    }

    /**
     * @param list<string> $names Region names.
     *
     * @return string Them as quoted PHP literals joined by commas.
     */
    private function literals(array $names): string
    {
        return implode(', ', array_map(static fn (string $name): string => var_export($name, true), $names));
    }

    /**
     * @param string $what What the class holds.
     *
     * @return string Its generated docblock.
     */
    private function generated(string $what): string
    {
        return $this->phpClassFile->generated('the configured theme (#154)', $what);
    }
}
