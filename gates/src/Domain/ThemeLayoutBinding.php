<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Component\Contracts\Component;

/**
 * One named layout of the theme (#154): `html--<name>.phtml`, `page--<name>.phtml` or both. A file it does not
 * ship falls back to the default layout's; a page it does ship may take its own page data and regions.
 */
final readonly class ThemeLayoutBinding
{
    /**
     * @param string                  $name     The layout's name, from its files' `--` suffix.
     * @param string                  $case     Its case in the generated `ThemeLayoutName` enum.
     * @param string                  $identity Its Tessera layout identity, `<theme>-<name>`.
     * @param TemplateBinding|null    $html     Its document template; null to fall back to `html.phtml`.
     * @param TemplateBinding|null    $page     Its page template; null to fall back to `page.phtml`.
     * @param class-string<Component> $pageData The page data its page template reads.
     * @param non-empty-list<string>  $regions  The page data's regions, in constructor order.
     */
    public function __construct(
        public string $name,
        public string $case,
        public string $identity,
        public ?TemplateBinding $html,
        public ?TemplateBinding $page,
        public string $pageData,
        public array $regions,
    ) {
    }

    /**
     * @return list<TemplateBinding> The templates it ships: its html, its page, or both.
     */
    public function templates(): array
    {
        $templates = [];

        if ($this->html !== null) {
            $templates[] = $this->html;
        }

        if ($this->page !== null) {
            $templates[] = $this->page;
        }

        return $templates;
    }
}
