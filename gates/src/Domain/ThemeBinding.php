<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Module\Contracts\ModuleTheme;

/** The build's validated selected theme and its document and page bindings. */
final readonly class ThemeBinding
{
    /**
     * @param string          $owner       The declaring site module.
     * @param ModuleTheme     $moduleTheme Its validated declaration and authored region schema.
     * @param TemplateBinding $html        The document wrapper binding.
     * @param TemplateBinding $page        The typed page binding.
     */
    public function __construct(
        public string $owner,
        public ModuleTheme $moduleTheme,
        public TemplateBinding $html,
        public TemplateBinding $page,
    ) {
    }
}
