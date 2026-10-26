<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\PresentationInventory;
use Gates\Domain\TemplateBinding;
use InvalidArgumentException;

use function array_diff;
use function array_fill_keys;
use function array_intersect_key;
use function array_map;
use function implode;
use function strtolower;

/** Plan and validate the theme's presentation (#153) before any output is published. */
final readonly class PresentationBuild
{
    /** @param string $root The project folder. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param string|null $theme The theme `config/app.php` names; null for an app with no HTML.
     *
     * @throws InvalidArgumentException When the theme breaks its layout, an identity repeats, or a template sits
     *                                  outside the theme.
     *
     * @return PresentationInventory The complete checked inputs, without generated or public writes.
     */
    public function plan(?string $theme): PresentationInventory
    {
        $themeBinding = (new ThemeBuild($this->root))->read($theme);
        $templates = $themeBinding === null
            ? []
            : [...$themeBinding->components, ...$themeBinding->layoutTemplates()];

        $this->claims($templates);

        return new PresentationInventory($templates, $themeBinding);
    }

    /**
     * @param list<TemplateBinding> $templates The complete template inventory.
     *
     * @throws InvalidArgumentException When a source, input or generated identity repeats, or a tpl file is out of place.
     */
    private function claims(array $templates): void
    {
        $claimed = [];

        foreach ($templates as $template) {
            $claims = array_fill_keys($this->identities($template), $template->source);

            if (array_intersect_key($claimed, $claims) !== []) {
                throw new InvalidArgumentException($template->source . ': duplicate presentation source, input or generated identity.');
            }

            $claimed += $claims;
        }

        $this->undeclared($templates);
    }

    /**
     * @param TemplateBinding $templateBinding One template binding.
     *
     * @return list<string> Independently unique source and output path claims, and the input for a component; every
     *                      html layout reads `ThemeLayout`, and pages may share their data (#154).
     */
    private function identities(TemplateBinding $templateBinding): array
    {
        return [
            'source:' . $templateBinding->source,
            ...($templateBinding->transparent() ? [] : ['input:' . strtolower($templateBinding->component)]),
            'output:' . $templateBinding->generated,
        ];
    }

    /**
     * @param list<TemplateBinding> $templates The theme's templates.
     *
     * @throws InvalidArgumentException When a template candidate sits anywhere the theme did not bind it.
     */
    private function undeclared(array $templates): void
    {
        $declaredPaths = new DeclaredPaths($this->root);
        $files = [
            ...$declaredPaths->templates('modules'),
            ...$declaredPaths->templates('themes'),
            ...$declaredPaths->templates('templates'),
        ];

        $sources = array_map(static fn (TemplateBinding $templateBinding): string => $templateBinding->source, $templates);
        $undeclared = array_diff($files, $sources);

        if ($undeclared !== []) {
            throw new InvalidArgumentException("Templates outside the theme's components and layouts: " . implode(', ', $undeclared)
                . '. Components live in themes/<theme>/src/Components/<Name>/, their templates in themes/<theme>/components/<Name>/.');
        }
    }
}
