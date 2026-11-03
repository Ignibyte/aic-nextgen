<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\LayoutName;
use AIC\Component\Contracts\PageLayouts;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\ThemeLayout;
use AIC\Component\Domain\ThemePage;
use AIC\Component\Domain\TrackedAssets;
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
 * The theme's layout lookups (#154): the `ThemeLayoutName` enum and `ThemeLayouts::create()` choosing each named
 * layout's identity and regions (the html adapter is ThemeHtmlSource's, the page adapter ThemePageSource's). Each is a `match` whose
 * `default` arm is the default layout, which is also the fallback for a file a named layout does not ship. They hold
 * types and lookups, never markup.
 */
final readonly class ThemeLayoutsSource
{
    /** The document and tracked-resource factory. */
    private const string LAYOUTS = <<<'PHP'
        final readonly class ThemeLayouts implements PageLayouts
        {
            /** Each layout's Tessera identity and region names, by its name; the empty name is the theme's own layout. */
            private const array LAYOUTS = [
        {rows}    ];

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
                $name = $this->name($layoutName);

                return new ThemeLayout(
                    new ThemePage($regions, $name),
                    $title,
                    $this->trackedAssets(),
                    self::LAYOUTS[$name]['identity'],
                    self::LAYOUTS[$name]['regions'],
                );
            }

            /**
             * @param LayoutName|null $layoutName The layout a controller named; null for the default.
             *
             * @return string Its name; empty for the theme's own layout.
             */
            private function name(?LayoutName $layoutName): string
            {
                return match ($layoutName) {
        {arms}            default => '',
                };
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
                    $this->assetPath($this->publicAssets->byPath({scriptPath}), 'pinned Tessera script'),{pageScripts}
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

    /** @param PhpClassFile $phpClassFile Emits checked generated source. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
    }

    /**
     * @param ThemeBinding $themeBinding The theme.
     * @param string       $scriptPath   The pinned Tessera script's alias.
     * @param list<string> $pageScripts  The theme's page scripts' aliases, in order (#239).
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string `ThemeLayouts`, choosing each named layout's identity and regions.
     */
    public function layouts(ThemeBinding $themeBinding, string $scriptPath, array $pageScripts = []): string
    {
        $rows = $this->row('', $themeBinding->name, $themeBinding->regions);

        foreach ($themeBinding->variants as $variant) {
            $rows .= $this->row($variant->name, $variant->identity, $variant->regions);
        }

        $arms = '';

        foreach ($themeBinding->variants as $variant) {
            $arms .= '            ThemeLayoutName::' . $variant->case . ' => ' . var_export($variant->name, true) . ",\n";
        }

        $source = strtr(self::LAYOUTS, [
            '{arms}' => $arms,
            '{pageScripts}' => $this->pageScripts($pageScripts),
            '{rows}' => $rows,
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
     * @param list<string> $pageScripts The theme's page scripts' aliases.
     *
     * @return string The third `TrackedAssets` argument, a list of their canonical URLs; nothing when there are none.
     */
    private function pageScripts(array $pageScripts): string
    {
        if ($pageScripts === []) {
            return '';
        }

        $paths = '';

        foreach ($pageScripts as $pageScript) {
            $paths .= "\n                \$this->assetPath(\$this->publicAssets->byPath(" . var_export($pageScript, true)
                . "), 'a theme page script'),";
        }

        return "\n            [" . $paths . "\n            ],";
    }

    /**
     * @param string       $name     A layout's name; empty for the theme's own.
     * @param string       $identity Its Tessera identity.
     * @param list<string> $regions  Its region names.
     *
     * @return string Its row in the generated `LAYOUTS` constant.
     */
    private function row(string $name, string $identity, array $regions): string
    {
        return '        ' . var_export($name, true) . " => [\n"
            . "            'identity' => " . var_export($identity, true) . ",\n"
            . "            'regions' => [" . $this->literals($regions) . "],\n"
            . "        ],\n";
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
