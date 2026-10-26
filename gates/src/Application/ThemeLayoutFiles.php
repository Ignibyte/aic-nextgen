<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\ThemeLayout;
use Gates\Domain\BuildPaths;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRole;
use Gates\Domain\ThemeLayoutBinding;
use InvalidArgumentException;

use function array_diff;
use function array_key_exists;
use function array_unique;
use function array_values;
use function file_get_contents;
use function implode;
use function in_array;
use function preg_match;
use function sort;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtok;
use function substr;
use function ucwords;

/**
 * A theme's named layouts (#154, #167). Besides `html.phtml` and `page.phtml`, `layouts/` may hold
 * `html--<name>.phtml` and `page--<name>.phtml`; nothing else. The page data classes those pages read sit with
 * `Page.php` in `src/Layouts/`. A name is lowercase words joined by single hyphens, and becomes a case of the
 * generated `ThemeLayoutName` enum.
 */
final readonly class ThemeLayoutFiles
{
    /** The templates every theme's `layouts/` holds. */
    public const array DEFAULTS = ['html.phtml', 'page.phtml'];

    /** The namespace of `src/Layouts/`'s classes. */
    private const string NAMESPACE = 'App\\Theme\\Layouts\\';

    /**
     * @param string        $root          The project folder.
     * @param ThemePageData $themePageData Reads a page data class's regions.
     */
    public function __construct(private string $root, private ThemePageData $themePageData = new ThemePageData())
    {
    }

    /**
     * @param string                 $theme   The theme's folder name.
     * @param list<string>           $entries `layouts/`'s entries, sorted.
     * @param list<string>           $classes `src/Layouts/`'s entries, sorted.
     * @param non-empty-list<string> $regions The default page's regions.
     *
     * @throws InvalidArgumentException When a file is out of place, a name is invalid or repeats as a case, a page's
     *                                  data class is not the theme's, lacks a `content` region, or a class is unused.
     *
     * @return list<ThemeLayoutBinding> The named layouts, in name order.
     */
    public function variants(string $theme, array $entries, array $classes, array $regions): array
    {
        $found = ['html' => [], 'page' => []];

        foreach (array_diff($entries, self::DEFAULTS) as $entry) {
            [$kind, $value] = $this->entry($theme, $entry);
            $found[$kind][] = $value;
        }

        $names = array_values(array_unique([...$found['html'], ...$found['page']]));
        sort($names);
        $variants = [];
        $used = ['Page'];

        foreach ($names as $name) {
            $ships = [in_array($name, $found['html'], true), in_array($name, $found['page'], true)];
            $variant = $this->variant($theme, $name, $ships, $regions);
            $used[] = substr($variant->pageData, strlen(self::NAMESPACE));
            $variants[] = $variant;
        }

        $this->cases($variants);
        $this->used($theme, $this->classes($theme, $classes), $used);

        return $variants;
    }

    /**
     * @param string $theme The theme.
     * @param string $entry One file in `layouts/`.
     *
     * @throws InvalidArgumentException When it is not a named layout file.
     *
     * @return array{'html'|'page', string} What it is, with the layout's name.
     */
    private function entry(string $theme, string $entry): array
    {
        foreach (['html', 'page'] as $role) {
            if (str_starts_with($entry, $role . '--') && str_ends_with($entry, '.phtml')) {
                return [$role, $this->name($theme, substr($entry, strlen($role) + 2, -strlen('.phtml')))];
            }
        }

        if (str_ends_with($entry, '.php')) {
            throw new InvalidArgumentException('themes/' . $theme . '/layouts/' . $entry . ': layouts/ holds templates only; '
                . 'a page data class goes in themes/' . $theme . '/src/Layouts/.');
        }

        throw new InvalidArgumentException('themes/' . $theme . '/layouts/' . $entry . ': a layout file is html.phtml, page.phtml, '
            . 'html--<name>.phtml or page--<name>.phtml.');
    }

    /**
     * @param string       $theme   The theme.
     * @param list<string> $entries `src/Layouts/`'s entries.
     *
     * @throws InvalidArgumentException When one is not a class file.
     *
     * @return list<string> The page data classes' short names, `Page` among them.
     */
    private function classes(string $theme, array $entries): array
    {
        $classes = [];

        foreach ($entries as $entry) {
            if (preg_match('/^[A-Z][A-Za-z0-9]*\\.php\\z/', $entry) !== 1) {
                throw new InvalidArgumentException(
                    'themes/' . $theme . '/src/Layouts/' . $entry . ': src/Layouts/ holds the page data classes only.',
                );
            }

            $classes[] = substr($entry, 0, -4);
        }

        return $classes;
    }

    /**
     * @param string $theme The theme.
     * @param string $name  A layout file's `--` suffix.
     *
     * @throws InvalidArgumentException When it is not lowercase words joined by single hyphens, or is `class`.
     *
     * @return string The name.
     */
    private function name(string $theme, string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*\\z/', $name) !== 1 || $name === 'class') {
            throw new InvalidArgumentException('themes/' . $theme . '/layouts: "' . $name . '" is no layout name; '
                . 'use lowercase words joined by single hyphens, not "class".');
        }

        return $name;
    }

    /**
     * @param string                 $theme   The theme.
     * @param string                 $name    The layout's name.
     * @param array{bool, bool}      $ships   Whether it ships `html--<name>.phtml`, and `page--<name>.phtml`.
     * @param non-empty-list<string> $regions The default page's regions.
     *
     * @throws InvalidArgumentException When its page's data class is not the theme's or lacks `content`.
     *
     * @return ThemeLayoutBinding The layout.
     */
    private function variant(string $theme, string $name, array $ships, array $regions): ThemeLayoutBinding
    {
        [$html, $page] = $ships;
        $pageData = $page
            ? $this->pageData($theme, $name)
            : $this->themePageData->page(ThemeBuild::PAGE);

        return new ThemeLayoutBinding(
            $name,
            $this->case($name),
            $theme . '-' . $name,
            $html ? $this->binding($theme, 'html', $name, ThemeLayout::class) : null,
            $page ? $this->binding($theme, 'page', $name, $pageData) : null,
            $pageData,
            $page ? $this->pageRegions($theme, $name, $pageData) : $regions,
        );
    }

    /**
     * @param string $theme The theme.
     * @param string $name  A page variant's name.
     *
     * @throws InvalidArgumentException When its typing line names no class in `src/Layouts/`.
     *
     * @return class-string<Component> The page data class its typing line names.
     */
    private function pageData(string $theme, string $name): string
    {
        $file = 'themes/' . $theme . '/layouts/page--' . $name . '.phtml';
        $line = (string) strtok((string) file_get_contents($this->root . '/' . $file), "\n");
        $start = strpos($line, '@var \\');
        $class = $start === false
            ? ''
            : (string) strtok(substr($line, $start + 6), ' ');

        if (preg_match('/^App\\\\Theme\\\\Layouts\\\\[A-Z][A-Za-z0-9]*\\z/', $class) !== 1) {
            throw new InvalidArgumentException($file . ': its first line names its page data, a class in src/Layouts/ such as '
                . '\\App\\Theme\\Layouts\\' . $this->case($name) . 'Page.');
        }

        return $this->themePageData->page($class);
    }

    /**
     * @param string                  $theme    The theme.
     * @param string                  $name     A page variant's name.
     * @param class-string<Component> $pageData Its page data.
     *
     * @throws InvalidArgumentException When the data is not page data or has no `content` region.
     *
     * @return non-empty-list<string> Its regions.
     */
    private function pageRegions(string $theme, string $name, string $pageData): array
    {
        $regions = $this->themePageData->regions($pageData);

        if (!in_array('content', $regions, true)) {
            throw new InvalidArgumentException('themes/' . $theme . '/layouts/page--' . $name . '.phtml: its page data '
                . $pageData . ' needs a content region, which a controller fills.');
        }

        return $regions;
    }

    /**
     * @param string                  $theme     The theme.
     * @param string                  $role      `html` or `page`.
     * @param string                  $name      The layout's name.
     * @param class-string<Component> $component The data the template reads.
     *
     * @return TemplateBinding Its template binding.
     */
    private function binding(string $theme, string $role, string $name, string $component): TemplateBinding
    {
        $file = 'themes/' . $theme . '/layouts/' . $role . '--' . $name . '.phtml';

        return new TemplateBinding(
            $component,
            $file,
            BuildPaths::GENERATED . '/templates/' . $file,
            $role === 'html'
                ? TemplateRole::Html
                : TemplateRole::Page,
        );
    }

    /**
     * @param list<ThemeLayoutBinding> $variants The named layouts.
     *
     * @throws InvalidArgumentException When two names make the same enum case.
     */
    private function cases(array $variants): void
    {
        $cases = [];

        foreach ($variants as $variant) {
            if (array_key_exists($variant->case, $cases)) {
                throw new InvalidArgumentException('The layouts ' . $cases[$variant->case] . ' and ' . $variant->name
                    . ' both make the ThemeLayoutName case ' . $variant->case . '; rename one.');
            }

            $cases[$variant->case] = $variant->name;
        }
    }

    /**
     * @param string       $theme   The theme.
     * @param list<string> $classes The page data classes in `layouts/`.
     * @param list<string> $used    The ones `Page` and the page variants read.
     *
     * @throws InvalidArgumentException When a class in `layouts/` is page data no page reads.
     */
    private function used(string $theme, array $classes, array $used): void
    {
        $unused = array_diff($classes, $used);

        if ($unused !== []) {
            throw new InvalidArgumentException('themes/' . $theme . '/src/Layouts: no page reads ' . implode(', ', $unused)
                . '; a page data class goes with the page--<name>.phtml whose first line names it.');
        }
    }

    /**
     * @param string $name A theme or layout name.
     *
     * @return string It in PascalCase: `focus` gives `Focus`, `two-col` gives `TwoCol`.
     */
    private function case(string $name): string
    {
        return str_replace('-', '', ucwords($name, '-'));
    }
}
