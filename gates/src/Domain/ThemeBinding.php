<?php

declare(strict_types=1);

namespace Gates\Domain;

/** The build's configured theme (#153, #154): its name, page regions, layouts, named layouts and components. */
final readonly class ThemeBinding
{
    /**
     * @param string                   $name       Its folder under `themes/`, also its Tessera layout identity.
     * @param non-empty-list<string>   $regions    The page's regions, in the order `layouts/Page.php` takes them.
     * @param TemplateBinding          $html       The document wrapper, `layouts/html.tpl.php`.
     * @param TemplateBinding          $page       The page, `layouts/page.tpl.php`, reading the region data.
     * @param list<TemplateBinding>    $components Each component's template, in folder name order.
     * @param list<ThemeLayoutBinding> $variants   The named layouts (#154), in name order.
     */
    public function __construct(
        public string $name,
        public array $regions,
        public TemplateBinding $html,
        public TemplateBinding $page,
        public array $components,
        public array $variants = [],
    ) {
    }

    /**
     * @return list<TemplateBinding> Every layout template: the default html and page, then each variant's own.
     */
    public function layoutTemplates(): array
    {
        $templates = [$this->html, $this->page];

        foreach ($this->variants as $variant) {
            $templates = [...$templates, ...$variant->templates()];
        }

        return $templates;
    }

    /**
     * @param string $source A page template's source.
     *
     * @return list<string> The regions its page data holds; none when no page layout has that source.
     */
    public function regionsOf(string $source): array
    {
        if ($source === $this->page->source) {
            return $this->regions;
        }

        foreach ($this->variants as $variant) {
            if ($variant->page !== null && $variant->page->source === $source) {
                return $variant->regions;
            }
        }

        return [];
    }
}
