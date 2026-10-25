<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\PresentationInventory;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateClass;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use Gates\Domain\TemplateReader;
use Gates\Domain\TemplateUnit;
use InvalidArgumentException;

use function array_fill_keys;
use function array_keys;
use function array_map;
use function file_get_contents;
use function glob;
use function in_array;
use function ksort;
use function lcfirst;
use function preg_match;
use function preg_quote;
use function sort;
use function strlen;
use function strrpos;
use function substr;

/**
 * Compiles every component template (#36, #97): each module's
 * `templates/components/X.html.php` and each page layout's
 * `templates/layouts/X.html.php`, read by the template reader (gate:25's
 * grammar, then the types) into `XTemplate.php` beside the component its
 * typing line names, in the component's namespace.
 */
final readonly class TemplateBuild
{
    /**
     * @param TemplateReader             $templateReader        Validates, types and lowers a template.
     * @param TemplateCompiler           $templateCompiler      Compiles the lowered template.
     * @param string                     $root                  The repo root.
     * @param PresentationInventory|null $presentationInventory The validated explicit inputs, with bounded legacy compatibility.
     */
    public function __construct(
        private TemplateReader $templateReader,
        private TemplateCompiler $templateCompiler,
        private string $root,
        private ?PresentationInventory $presentationInventory = null,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a template cannot be read, is refused, or is too big for one class.
     *
     * @return array<string, string> Each generated class's source, by its file, sorted.
     */
    public function classes(): array
    {
        $classes = [];

        foreach ($this->units() as $path => $templateUnit) {
            $generated = $templateUnit->binding === null
                ? $templateUnit->generated
                : $this->root . '/' . $templateUnit->generated;
            $classes[$generated] = $this->compile($path, $templateUnit)->source;
        }

        ksort($classes);

        return $classes;
    }

    /**
     * @throws InvalidArgumentException When a template cannot be read, is refused, or is too big for one class.
     *
     * @return array<string, bool> Whether each template's `render()` takes the render pass, by its component class,
     *                             fully qualified, in template order (#98's lookup).
     */
    public function components(): array
    {
        $components = [];

        foreach ($this->units() as $path => $templateUnit) {
            $components[$templateUnit->component] = $this->compile($path, $templateUnit)->takesPass;
        }

        return $components;
    }

    /**
     * @param list<TemplateNode> $nodes      The typed page template nodes.
     * @param list<string>       $properties Declared Region property names.
     * @param string             $component  The page data class.
     *
     * @throws InvalidArgumentException When a region is missing, repeated or placed only conditionally.
     */
    public function regions(array $nodes, array $properties, string $component): void
    {
        $variable = lcfirst(substr($component, (int) strrpos($component, '\\') + 1));
        $pattern = '/^\\$renderPass->region\\(\\$' . preg_quote($variable, '/') . '->([A-Za-z_][A-Za-z0-9_]*)\\)\\z/';
        $actual = $this->regionCounts($nodes, $pattern);
        $expected = array_fill_keys($properties, 1);
        ksort($expected);

        if ($actual !== $expected) {
            throw new InvalidArgumentException('The page template must render each declared Region property exactly once on every path.');
        }
    }

    /**
     * @param list<TemplateNode> $nodes The typed HTML wrapper.
     *
     * @throws InvalidArgumentException When the document omits or repeats its page or tracked resources.
     */
    public function document(array $nodes): void
    {
        $pattern = '/^\\$renderPass->inline\\(\\$themeLayout->(page|trackedAssets)\\)\\z/';

        if ($this->regionCounts($nodes, $pattern) !== ['page' => 1, 'trackedAssets' => 1]) {
            throw new InvalidArgumentException('The HTML template must render its page and tracked assets exactly once on every path.');
        }
    }

    /**
     * @throws InvalidArgumentException When a template cannot be read or is refused.
     *
     * @return array<string, TemplateUnit> Each template read, by its path relative to the repo root.
     */
    private function units(): array
    {
        $units = [];

        foreach ($this->legacyTemplates() as $template) {
            $path = substr($template, strlen($this->root) + 1);
            $units[$path] = $this->read($path, null);
        }

        foreach ($this->presentationInventory->templates ?? [] as $binding) {
            $components = array_map(static fn (TemplateUnit $templateUnit): string => $templateUnit->component, $units);

            if (in_array($binding->component, $components, true)) {
                throw new InvalidArgumentException($binding->source . ': component already has a legacy or declared template.');
            }

            $units[$binding->source] = $this->read($binding->source, $binding);
        }

        return $units;
    }

    /**
     * @param string       $path         The template, relative to the repo root.
     * @param TemplateUnit $templateUnit The template read.
     *
     * @throws InvalidArgumentException When it is too big for one class, or a loop too big or too complex for one part:
     *                                  `path: detail`.
     *
     * @return TemplateClass The generated class.
     */
    private function compile(string $path, TemplateUnit $templateUnit): TemplateClass
    {
        $component = $templateUnit->component;
        $split = (int) strrpos($component, '\\');

        try {
            return $this->templateCompiler->compile(
                $templateUnit->nodes,
                substr($component, 0, $split),
                substr($component, $split + 1),
                $templateUnit->binding,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException($path . ': ' . $invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }
    }

    /**
     * @param string               $path            The project-relative authored source.
     * @param TemplateBinding|null $templateBinding Its explicit declaration, absent for a bounded legacy input.
     *
     * @throws InvalidArgumentException When the source cannot be read or validated.
     *
     * @return TemplateUnit The checked and lowered source.
     */
    private function read(string $path, ?TemplateBinding $templateBinding): TemplateUnit
    {
        $source = file_get_contents($this->root . '/' . $path);

        if ($source === false) {
            throw new InvalidArgumentException('Cannot read ' . $path . '.');
        }

        $templateUnit = $this->templateReader->read($path, $source, $templateBinding);
        $this->placement($templateUnit);

        return $templateUnit;
    }

    /**
     * @param TemplateUnit $templateUnit The typed source, before any compiled output is emitted.
     *
     * @throws InvalidArgumentException When the selected wrappers do not preserve the same render tree.
     */
    private function placement(TemplateUnit $templateUnit): void
    {
        $theme = $this->presentationInventory->theme ?? null;

        if ($theme === null) {
            return;
        }

        $source = $templateUnit->binding->source ?? '';

        if ($source === $theme->page->source) {
            $this->regions($templateUnit->nodes, array_keys($theme->moduleTheme->regions), $templateUnit->component);
        }

        if ($source !== $theme->html->source) {
            return;
        }

        $this->document($templateUnit->nodes);
    }

    /**
     * @return list<string> The sole bounded old-input bridge, removed by the complete reference migration.
     */
    private function legacyTemplates(): array
    {
        $components = glob($this->root . '/modules/*/templates/components/*.html.php');
        $layouts = glob($this->root . '/templates/layouts/*.html.php');
        $files = [...($components === false ? [] : $components), ...($layouts === false ? [] : $layouts)];
        sort($files);

        return $files;
    }

    /**
     * @param list<TemplateNode> $nodes   One sequential template body.
     * @param non-empty-string   $pattern The exact lowered output expression.
     *
     * @throws InvalidArgumentException When control flow cannot preserve unique regions.
     *
     * @return array<string, int> Render counts common to all paths, sorted by property.
     */
    private function regionCounts(array $nodes, string $pattern): array
    {
        $counts = [];

        foreach ($nodes as $node) {
            foreach ($this->nodeRegions($node, $pattern) as $property => $count) {
                $counts[$property] = ($counts[$property] ?? 0) + $count;
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param TemplateNode     $templateNode One lowered node.
     * @param non-empty-string $pattern      The exact lowered output expression.
     *
     * @throws InvalidArgumentException When a branch or loop cannot guarantee each region once.
     *
     * @return array<string, int> Its region counts.
     */
    private function nodeRegions(TemplateNode $templateNode, string $pattern): array
    {
        if ($templateNode->kind === TemplateNodeKind::Raw) {
            return preg_match($pattern, $templateNode->code, $matches) === 1
                ? [$matches[1] ?? '' => 1]
                : [];
        }

        $children = $this->regionCounts($templateNode->children, $pattern);
        $otherwise = $this->regionCounts($templateNode->otherwise, $pattern);

        if ($children !== $otherwise) {
            throw new InvalidArgumentException('Page regions must occur equally in every branch and cannot occur in a loop.');
        }

        return $children;
    }
}
