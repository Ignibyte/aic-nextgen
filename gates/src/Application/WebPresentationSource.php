<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\RenderPass;
use AIC\Component\Contracts\PageLayouts;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\ThemeLayout;
use AIC\Component\Domain\ThemePage;
use AIC\Component\Domain\TrackedAssets;
use AIC\Component\Exception\UnknownComponent;
use AIC\Http\Contracts\PublicAsset;
use AIC\Http\Contracts\PublicAssets;
use AIC\Module\Application\WebPresentation;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;
use Override;

use function array_map;
use function array_values;
use function implode;
use function strtr;
use function var_export;

/** Generate the selected theme and the inert presentation catalogs it consumes. */
final readonly class WebPresentationSource
{
    /** The legacy-compatible catalog factory. */
    private const string SOURCE = <<<'PHP'
        final readonly class Presentation
        {
            /**
             * @throws InvalidArgumentException When compiled asset metadata is invalid.
             *
             * @return WebPresentation The compiled template and public asset catalogs.
             */
            public function create(): WebPresentation
            {
                return new WebPresentation(new ModuleTemplates(), new Assets());
            }
        }
        PHP;

    /** The selected theme shares the same public catalog as early static delivery. */
    private const string THEMED = <<<'PHP'
        final readonly class Presentation
        {
            /**
             * @throws InvalidArgumentException When compiled asset metadata is invalid.
             *
             * @return WebPresentation The compiled template, asset and layout catalogs.
             */
            public function create(): WebPresentation
            {
                $assets = new Assets();

                return new WebPresentation(new ModuleTemplates(), $assets, new ThemeLayouts($assets));
            }
        }
        PHP;

    /** The generated theme owns document and tracked-resource composition. */
    private const string LAYOUTS = <<<'PHP'
        final readonly class ThemeLayouts implements PageLayouts
        {
            /** @param PublicAssets $publicAssets The same compiled catalog used for public delivery. */
            public function __construct(private PublicAssets $publicAssets)
            {
            }

            /**
             * @param string  $title   The document title.
             * @param Regions $regions The filled regions shared with fragment rendering.
             *
             * @throws InvalidArgumentException When required public resources are absent.
             *
             * @return ThemeLayout The selected document over those same regions.
             */
            #[Override]
            public function create(string $title, Regions $regions): ThemeLayout
            {
                return new ThemeLayout(new ThemePage($regions), $title, $this->trackedAssets(), $this->identity(), $this->names());
            }

            /** @return list<string> The explicitly declared region names. */
            private function names(): array
            {
                return [{regions}];
            }

            /** @return string The selected theme's stable layout identity. */
            private function identity(): string
            {
                return {identity};
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

    /** Only the compiled adapter constructs authored page data. */
    private const string PAGE = <<<'PHP'
        final readonly class ThemePageTemplate
        {
            /**
             * @param ThemePage  $themePage  The full document's shared filled regions.
             * @param RenderPass $renderPass The same pass used by targeted fragments.
             *
             * @throws InvalidArgumentException When a region cannot be constructed.
             * @throws UnknownComponent         When a child has no registered template.
             *
             * @return string The authored page markup without additional markers.
             */
            public function render(ThemePage $themePage, RenderPass $renderPass): string
            {
                return (new {template}())->render(
                    new {page}(
                        {arguments},
                    ),
                    $renderPass,
                );
            }
        }
        PHP;

    /** @param PhpClassFile $phpClassFile Emits checked generated source. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
    }

    /**
     * @param ThemeBinding|null $themeBinding The explicit selection, absent for bounded legacy apps.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string The inert presentation factory.
     */
    public function file(?ThemeBinding $themeBinding = null): string
    {
        $namespace = 'App\\Generated';
        $classes = [WebPresentation::class, InvalidArgumentException::class, $namespace . '\\ModuleTemplates', $namespace . '\\Assets'];
        $source = $themeBinding === null
            ? self::SOURCE
            : self::THEMED;

        return $this->phpClassFile->file(
            $namespace,
            $classes,
            [],
            $this->phpClassFile->generated('the module presentation catalogs', 'the inert presentation factory.'),
            $source,
        );
    }

    /**
     * @param ThemeBinding $themeBinding The validated theme and region schema.
     * @param string       $scriptPath   The configured pinned Tessera alias compiled from its release declaration.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string The literal selected-theme factory.
     */
    public function layouts(ThemeBinding $themeBinding, string $scriptPath): string
    {
        $literals = array_map(
            static fn (string $name): string => var_export($name, true),
            array_values($themeBinding->moduleTheme->regions),
        );
        $source = strtr(self::LAYOUTS, [
            '{identity}' => var_export($themeBinding->moduleTheme->name, true),
            '{regions}' => implode(', ', $literals),
            '{scriptPath}' => var_export($scriptPath, true),
        ]);
        $classes = [PageLayouts::class, Regions::class, ThemeLayout::class, ThemePage::class, PublicAssets::class,
            PublicAsset::class, TrackedAssets::class, InvalidArgumentException::class, Override::class];

        return $this->phpClassFile->file(
            'App\\Generated',
            $classes,
            [],
            $this->phpClassFile->generated('the selected theme declaration', 'the document and tracked resource factory.'),
            $source,
        );
    }

    /**
     * @param ThemeBinding $themeBinding The authored page input and its compiled template.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string The transparent typed page adapter.
     */
    public function pageTemplate(ThemeBinding $themeBinding): string
    {
        $literals = array_map(
            static fn (string $name): string => var_export($name, true),
            array_values($themeBinding->moduleTheme->regions),
        );
        $arguments = array_map(static fn (string $name): string => '$themePage->regions->region(' . $name . ')', $literals);
        $classes = [ThemePage::class, RenderPass::class, InvalidArgumentException::class, UnknownComponent::class,
            $themeBinding->page->component, $themeBinding->page->templateClass];
        $source = strtr(self::PAGE, ['{arguments}' => implode(",\n                ", $arguments),
            '{page}' => $this->phpClassFile->short($themeBinding->page->component),
            '{template}' => $this->phpClassFile->short($themeBinding->page->templateClass)]);

        return $this->phpClassFile->file(
            'App\\Generated',
            $classes,
            [],
            $this->phpClassFile->generated('the selected theme declaration', 'the typed page rendering adapter.'),
            $source,
        );
    }
}
