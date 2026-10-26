<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\PresentationInventory;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateClass;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use Gates\Domain\TemplateReader;
use Gates\Domain\TemplateRole;
use Gates\Domain\TemplateUnit;
use InvalidArgumentException;

use function array_fill_keys;
use function array_map;
use function file_get_contents;
use function in_array;
use function ksort;
use function lcfirst;
use function preg_match;
use function preg_quote;
use function strrpos;
use function substr;

/**
 * Compiles the theme's templates (#153) into generated/templates. Input identity and
 * placement are validated before output.
 */
final readonly class TemplateBuild
{
    /**
     * @param TemplateReader        $templateReader        Validates, types and lowers a template.
     * @param TemplateCompiler      $templateCompiler      Compiles the lowered template.
     * @param string                $root                  The repo root.
     * @param PresentationInventory $presentationInventory The theme's checked templates.
     */
    public function __construct(
        private TemplateReader $templateReader,
        private TemplateCompiler $templateCompiler,
        private string $root,
        private PresentationInventory $presentationInventory,
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
            $generated = $this->root . '/' . $templateUnit->binding->generated;
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
            $components[$templateUnit->binding->component] = $this->compile($path, $templateUnit)->takesPass;
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
        $presentationInventory = $this->presentationInventory;

        foreach ($presentationInventory->templates as $binding) {
            $components = array_map(static fn (TemplateUnit $templateUnit): string => $templateUnit->binding->component, $units);

            if (!$binding->transparent() && in_array($binding->component, $components, true)) {
                throw new InvalidArgumentException($binding->source . ': component already has a declared template.');
            }

            $units[$binding->source] = $this->read($binding->source, $binding, $presentationInventory);
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
        try {
            return $this->templateCompiler->compile(
                $templateUnit->nodes,
                $templateUnit->binding,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException($path . ': ' . $invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }
    }

    /**
     * @param string                $path                  The project-relative authored source.
     * @param TemplateBinding       $templateBinding       Its explicit declaration.
     * @param PresentationInventory $presentationInventory The actual checked theme and component declarations.
     *
     * @throws InvalidArgumentException When the source cannot be read or validated.
     *
     * @return TemplateUnit The checked and lowered source.
     */
    private function read(
        string $path,
        TemplateBinding $templateBinding,
        PresentationInventory $presentationInventory,
    ): TemplateUnit {
        $source = file_get_contents($this->root . '/' . $path);

        if ($source === false) {
            throw new InvalidArgumentException('Cannot read ' . $path . '.');
        }

        $templateUnit = $this->templateReader->read($path, $source, $templateBinding);
        $this->placement($templateUnit, $presentationInventory);

        return $templateUnit;
    }

    /**
     * @param TemplateUnit          $templateUnit          The typed source, before any compiled output is emitted.
     * @param PresentationInventory $presentationInventory The actual checked declarations, including default consumers.
     *
     * @throws InvalidArgumentException When the selected wrappers do not preserve the same render tree.
     */
    private function placement(TemplateUnit $templateUnit, PresentationInventory $presentationInventory): void
    {
        $theme = $presentationInventory->theme;

        if ($theme === null) {
            return;
        }

        if ($templateUnit->binding->role === TemplateRole::Page) {
            $this->regions($templateUnit->nodes, $theme->regionsOf($templateUnit->binding->source), $templateUnit->binding->component);
        }

        if ($templateUnit->binding->role !== TemplateRole::Html) {
            return;
        }

        $this->document($templateUnit->nodes);
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
