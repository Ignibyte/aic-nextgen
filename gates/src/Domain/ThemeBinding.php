<?php

declare(strict_types=1);

namespace Gates\Domain;

/** The build's configured theme (#153): its name, page regions, layouts and components. */
final readonly class ThemeBinding
{
    /**
     * @param string                 $name       Its folder under `themes/`, also its Tessera layout identity.
     * @param non-empty-list<string> $regions    The page's regions, in the order `layouts/Page.php` takes them.
     * @param TemplateBinding        $html       The document wrapper, `layouts/html.tpl.php`.
     * @param TemplateBinding        $page       The page, `layouts/page.tpl.php`, reading the region data.
     * @param list<TemplateBinding>  $components Each component's template, in folder name order.
     */
    public function __construct(
        public string $name,
        public array $regions,
        public TemplateBinding $html,
        public TemplateBinding $page,
        public array $components,
    ) {
    }
}
