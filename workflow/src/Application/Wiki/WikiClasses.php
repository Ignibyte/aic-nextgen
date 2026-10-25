<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Wiki;

use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Domain\Console\SurfaceDeclarations;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function explode;
use function in_array;
use function ksort;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strrchr;
use function substr;

/**
 * A module page's classes (#108), by the `src/` folder their kind puts them
 * in (gate:26): each class with its file, and each component with its
 * template and the template class the build compiles beside it.
 */
final readonly class WikiClasses
{
    /**
     * The class-like kinds the index records.
     */
    private const array CLASS_LIKE = ['class', 'interface', 'enum', 'trait'];

    /**
     * @param WikiMarkdown $wikiMarkdown Tables and spans.
     * @param WikiLinks    $wikiLinks    Links to the files.
     */
    public function __construct(
        private WikiMarkdown $wikiMarkdown,
        private WikiLinks $wikiLinks,
    ) {
    }

    /**
     * @param string              $module              The module.
     * @param list<CodeSymbol>    $symbols             The symbols in its files.
     * @param list<string>        $files               Its files as the index lists them.
     * @param SurfaceDeclarations $surfaceDeclarations The actual declared template mappings.
     *
     * @return list<string> The classes section's lines.
     */
    public function lines(string $module, array $symbols, array $files, SurfaceDeclarations $surfaceDeclarations): array
    {
        $folders = self::folders($module, $symbols);
        $lines = [];

        foreach (array_keys($folders) as $folder) {
            $lines = [...$lines, ...$this->folder($module, $folder, $folders[$folder], $files, $surfaceDeclarations)];
        }

        return [...$this->wikiMarkdown->section('## Classes', $folders === [] ? ['none'] : []), ...$lines];
    }

    /**
     * @param list<CodeSymbol> $symbols A module's symbols.
     *
     * @return int How many class-likes they hold.
     */
    public function count(array $symbols): int
    {
        return count(array_filter($symbols, self::isClass(...)));
    }

    /**
     * @param string              $module              The module.
     * @param string              $folder              A folder under its `src/`.
     * @param list<CodeSymbol>    $symbols             The folder's classes.
     * @param list<string>        $files               The module's files.
     * @param SurfaceDeclarations $surfaceDeclarations The actual declared template mappings.
     *
     * @return list<string> The folder's subsection: components with their templates, any other folder's classes with their files.
     */
    private function folder(
        string $module,
        string $folder,
        array $symbols,
        array $files,
        SurfaceDeclarations $surfaceDeclarations,
    ): array {
        $component = $folder === 'Component';
        $table = $this->wikiMarkdown->table(
            $component ? ['Component', 'Template', 'Template class'] : ['Class', 'File'],
            $component ? $this->components($module, $symbols, $files, $surfaceDeclarations) : $this->classes($symbols),
            'bin/aic code:tree ' . $module,
        );

        return $this->wikiMarkdown->section('### ' . $this->wikiMarkdown->code('src/' . $folder . '/'), $table);
    }

    /**
     * @param list<CodeSymbol> $symbols The classes of a folder.
     *
     * @return list<list<string>> Each class and its file, linked.
     */
    private function classes(array $symbols): array
    {
        $rows = [];

        foreach ($symbols as $symbol) {
            $rows[] = [$this->wikiMarkdown->code($symbol->name), $this->fileLink($symbol->file)];
        }

        return $rows;
    }

    /**
     * @param string              $module              The module.
     * @param list<CodeSymbol>    $symbols             The classes of its `src/Component/`.
     * @param list<string>        $files               Its files.
     * @param SurfaceDeclarations $surfaceDeclarations The actual declared template mappings.
     *
     * @return list<list<string>> Each component with its template file and template class; the template classes are not rows.
     */
    private function components(
        string $module,
        array $symbols,
        array $files,
        SurfaceDeclarations $surfaceDeclarations,
    ): array {
        $names = array_map(static fn (CodeSymbol $codeSymbol): string => $codeSymbol->name, $symbols);
        $components = array_filter(
            $symbols,
            static fn (CodeSymbol $codeSymbol): bool => !str_ends_with($codeSymbol->name, 'Template')
                || !in_array(substr($codeSymbol->name, 0, -8), $names, true),
        );

        return array_values(array_map(
            fn (CodeSymbol $codeSymbol): array => $this->component($module, $codeSymbol->name, $names, $files, $surfaceDeclarations),
            $components,
        ));
    }

    /**
     * @param string              $module              The module.
     * @param string              $class               A component.
     * @param list<string>        $names               The classes of its folder.
     * @param list<string>        $files               The module's files.
     * @param SurfaceDeclarations $surfaceDeclarations The actual declared template mappings.
     *
     * @return list<string> Its row: the class, its template file, its template class.
     */
    private function component(
        string $module,
        string $class,
        array $names,
        array $files,
        SurfaceDeclarations $surfaceDeclarations,
    ): array {
        foreach ($surfaceDeclarations->templates as $mapping) {
            if (($mapping['component'] ?? '') === $class) {
                return [$this->wikiMarkdown->code($class), $this->fileLink($mapping['source'] ?? ''),
                    $this->wikiMarkdown->code($mapping['templateClass'] ?? '')];
            }
        }

        $template = 'modules/' . $module . '/templates/components/' . self::short($class) . '.html.php';

        return [
            $this->wikiMarkdown->code($class),
            in_array($template, $files, true) ? $this->fileLink($template) : 'none',
            in_array($class . 'Template', $names, true) ? $this->wikiMarkdown->code($class . 'Template') : 'none',
        ];
    }

    /**
     * @param string $path A file in the project.
     *
     * @return string A link to it from a module page.
     */
    private function fileLink(string $path): string
    {
        return $this->wikiMarkdown->link($path, $this->wikiLinks->file($path, false));
    }

    /**
     * @param CodeSymbol $codeSymbol A symbol.
     *
     * @return bool Whether it is a class-like.
     */
    private static function isClass(CodeSymbol $codeSymbol): bool
    {
        return in_array($codeSymbol->kind, self::CLASS_LIKE, true);
    }

    /**
     * @param string           $module  The module.
     * @param list<CodeSymbol> $symbols The symbols in its files.
     *
     * @return array<string, list<CodeSymbol>> Its class-likes under `src/`, by folder, folders sorted.
     */
    private static function folders(string $module, array $symbols): array
    {
        $prefix = 'modules/' . $module . '/src/';
        $folders = [];

        foreach ($symbols as $symbol) {
            if (!in_array($symbol->kind, self::CLASS_LIKE, true) || !str_starts_with($symbol->file, $prefix)) {
                continue;
            }

            $folders[explode('/', substr($symbol->file, strlen($prefix)))[0]][] = $symbol;
        }

        ksort($folders);

        return $folders;
    }

    /**
     * @param string $class A class's full name.
     *
     * @return string Its last segment.
     */
    private static function short(string $class): string
    {
        $last = strrchr($class, '\\');

        return $last === false
            ? $class
            : substr($last, 1);
    }
}
