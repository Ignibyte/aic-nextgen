<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\RenderPass;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\ThemePage;
use AIC\Component\Exception\UnknownComponent;
use Gates\Domain\TemplateBinding;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;

use function array_map;
use function implode;
use function strtr;
use function var_export;

/**
 * The theme's page adapter (#154): `ThemePageTemplate` builds the typed page data of the layout a page was given and
 * renders its page template, `page.tpl.php`'s through the `default` arm when the layout ships no page of its own. It
 * holds types and lookups, never markup.
 */
final readonly class ThemePageSource
{
    /** The page adapter: builds the layout's typed page data and renders its page template. */
    private const string PAGE = <<<'PHP'
        final readonly class ThemePageTemplate
        {
            /**
             * @param ThemePage  $themePage  The document's filled regions and its layout's key.
             * @param RenderPass $renderPass The same pass used by targeted fragments.
             *
             * @throws InvalidArgumentException When a region cannot be constructed.
             * @throws UnknownComponent         When a child has no registered template.
             *
             * @return string The layout's page markup; page.tpl.php's when it ships no page of its own.
             */
            public function render(ThemePage $themePage, RenderPass $renderPass): string
            {
                return match ($themePage->layout) {
        {arms}            default => $this->page($themePage->regions, $renderPass),
                };
            }
        {methods}}
        PHP;

    /** One page layout's renderer in the page adapter. */
    private const string PAGE_METHOD = <<<'PHP'

            /**
             * @param Regions    $regions    The page's regions.
             * @param RenderPass $renderPass The same pass used by targeted fragments.
             *
             * @throws InvalidArgumentException When a region cannot be constructed.
             * @throws UnknownComponent         When a child has no registered template.
             *
             * @return string {source}'s markup.
             */
            private function {method}(Regions $regions, RenderPass $renderPass): string
            {
                return (new {template}())->render(
                    new {page}(
                        {arguments},
                    ),
                    $renderPass,
                );
            }

        PHP;

    /** @param PhpClassFile $phpClassFile Emits checked generated source. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
    }

    /**
     * @param ThemeBinding $themeBinding The theme.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string `ThemePageTemplate`, rendering each layout's page or `page.tpl.php`.
     */
    public function pageTemplate(ThemeBinding $themeBinding): string
    {
        $arms = '';
        $methods = $this->pageMethod('page', $themeBinding->page, $themeBinding->regions);
        $classes = [ThemePage::class, RenderPass::class, Regions::class, InvalidArgumentException::class, UnknownComponent::class,
            $themeBinding->page->component, $themeBinding->page->templateClass];

        foreach ($themeBinding->variants as $variant) {
            $page = $variant->page;

            if ($page === null) {
                continue;
            }

            $arms .= '            ' . var_export($variant->name, true) . ' => $this->page' . $variant->case
                . "(\$themePage->regions, \$renderPass),\n";
            $methods .= $this->pageMethod('page' . $variant->case, $page, $variant->regions);
            $classes = [...$classes, $page->component, $page->templateClass];
        }

        $source = strtr(self::PAGE, ['{arms}' => $arms, '{methods}' => $methods]);
        $docblock = $this->phpClassFile->generated('the configured theme (#154)', 'the typed page adapter.');

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param string                 $method          The method's name.
     * @param TemplateBinding        $templateBinding The page template.
     * @param non-empty-list<string> $regions         Its page data's regions.
     *
     * @return string The method rendering that page.
     */
    private function pageMethod(string $method, TemplateBinding $templateBinding, array $regions): string
    {
        $arguments = array_map(static fn (string $name): string => '$regions->region(' . var_export($name, true) . ')', $regions);

        return strtr(self::PAGE_METHOD, [
            '{arguments}' => implode(",\n                ", $arguments),
            '{method}' => $method,
            '{page}' => $this->phpClassFile->short($templateBinding->component),
            '{source}' => $templateBinding->source,
            '{template}' => $this->phpClassFile->short($templateBinding->templateClass),
        ]);
    }
}
