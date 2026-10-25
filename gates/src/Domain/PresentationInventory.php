<?php

declare(strict_types=1);

namespace Gates\Domain;

/** Complete validated presentation inputs, fixed before generated or public files are emitted. */
final readonly class PresentationInventory
{
    /**
     * @param list<TemplateBinding> $templates The explicit component, document and page bindings.
     * @param ThemeBinding|null     $theme     The selected theme; null when no module selects a theme.
     */
    public function __construct(
        public array $templates,
        public ?ThemeBinding $theme,
    ) {
    }
}
