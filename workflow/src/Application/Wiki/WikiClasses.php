<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Wiki;

use AIC\Workflow\Domain\CodeSymbol;

use function array_filter;
use function array_keys;
use function count;
use function explode;
use function in_array;
use function ksort;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * A module page's classes (#108), by the `src/` folder their kind puts them
 * in (gate:26): each class with its file, and each component with its
 * source and generated class taken from the actual declaration inventory.
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
     * @param string           $module  The module.
     * @param list<CodeSymbol> $symbols The symbols in its files.
     *
     * @return list<string> The classes section's lines.
     */
    public function lines(string $module, array $symbols): array
    {
        $folders = self::folders($module, $symbols);
        $lines = [];

        foreach (array_keys($folders) as $folder) {
            $lines = [...$lines, ...$this->folder($module, $folder, $folders[$folder])];
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
     * @param string           $module  The module.
     * @param string           $folder  A folder under its `src/`.
     * @param list<CodeSymbol> $symbols The folder's classes.
     *
     * @return list<string> The folder's subsection: its classes with their files.
     */
    private function folder(string $module, string $folder, array $symbols): array
    {
        $table = $this->wikiMarkdown->table(['Class', 'File'], $this->classes($symbols), 'bin/aic code:tree ' . $module);

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
}
