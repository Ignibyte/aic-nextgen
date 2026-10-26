<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeAnswer;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_search;
use function array_slice;
use function basename;
use function count;
use function dirname;
use function explode;
use function implode;
use function ksort;
use function str_starts_with;
use function substr;

/**
 * An area's or a path's outline in the code index (#106): its files grouped
 * by folder, each with its class-likes and methods and their lines; for a
 * module its surface first; for a system its layers in the overview.
 */
final readonly class CodeOutline
{
    /**
     * @param CodeLookup     $codeLookup     Reads the index.
     * @param CodeModuleView $codeModuleView A module's surface.
     */
    public function __construct(
        private CodeLookup $codeLookup,
        private CodeModuleView $codeModuleView,
    ) {
    }

    /**
     * @param string $area `module:<m>` or `system:<S>`.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return CodeAnswer Its outline.
     */
    public function area(string $area): CodeAnswer
    {
        $symbols = $this->codeLookup->inArea($area);
        $module = str_starts_with($area, 'module:')
            ? substr($area, 7)
            : '';
        $prefix = match (true) {
            $module !== '' => 'modules/' . $module . '/',
            str_starts_with($area, 'theme:') => 'themes/' . substr($area, 6) . '/',
            default => $this->systemPrefix($symbols),
        };
        $files = $prefix === ''
            ? []
            : $this->codeLookup->filesUnder($prefix);
        $surface = $module === ''
            ? []
            : $this->codeModuleView->surface($module, $symbols);

        return new CodeAnswer(
            [$area . ' — ' . count($files) . ' files', ...$surface, ...$this->outline($files, $symbols)],
            $files,
            [$area],
        );
    }

    /**
     * @param string $path A path, or the start of one.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return CodeAnswer The outline of the files under it.
     */
    public function path(string $path): CodeAnswer
    {
        $files = $this->codeLookup->filesUnder($path);
        $lines = ['path:' . $path . ' — ' . count($files) . ' files', ...$this->outline($files, $this->codeLookup->symbolsUnder($path))];

        return new CodeAnswer($lines, $files, ['path:' . $path]);
    }

    /**
     * @param string $area `system:<S>`.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return string Its class-likes counted per layer, in brackets; empty when it has none.
     */
    public function layers(string $area): string
    {
        $layers = [];

        foreach ($this->codeLookup->inArea($area) as $codeSymbol) {
            $layer = $this->layer($codeSymbol->file);
            $layers[$layer] = ($layers[$layer] ?? 0) + ($codeSymbol->kind === 'method' ? 0 : 1);
        }

        ksort($layers);
        $named = [];

        foreach ($layers as $layer => $count) {
            $named[] = $layer . ' ' . $count;
        }

        return $named === []
            ? ''
            : ' (' . implode(', ', $named) . ')';
    }

    /**
     * @param list<string>     $files   An area's files.
     * @param list<CodeSymbol> $symbols Their symbols.
     *
     * @return list<string> The files by folder, each with what it declares.
     */
    private function outline(array $files, array $symbols): array
    {
        $byFile = [];

        foreach ($symbols as $symbol) {
            $byFile[$symbol->file][] = $symbol;
        }

        $lines = [];
        $folder = '';

        foreach ($files as $file) {
            $lines = dirname($file) === $folder
                ? $lines
                : [...$lines, dirname($file) . '/'];
            $folder = dirname($file);
            $lines[] = '  ' . basename($file) . $this->declared($byFile[$file] ?? []);
        }

        return $lines;
    }

    /**
     * @param list<CodeSymbol> $symbols A file's symbols.
     *
     * @return string Its class-likes and methods with their lines; the surface's pseudo-symbols left out.
     */
    private function declared(array $symbols): string
    {
        $parts = [];

        foreach (array_filter($symbols, static fn (CodeSymbol $codeSymbol): bool => $codeSymbol->line > 0) as $codeSymbol) {
            $parts[] = $codeSymbol->kind === 'method'
                ? (explode('::', $codeSymbol->name)[1] ?? '') . ' :' . $codeSymbol->line
                : $codeSymbol->kind . ' ' . $codeSymbol->name . ' :' . $codeSymbol->line;
        }

        return $parts === []
            ? ''
            : ' — ' . implode(', ', $parts);
    }

    /**
     * @param list<CodeSymbol> $symbols A system's symbols.
     *
     * @return string The folder its files sit in, with its slash; empty when it has none.
     */
    private function systemPrefix(array $symbols): string
    {
        $parts = explode('/', $symbols[0]->file ?? '');
        $index = array_search('src', $parts, true);

        return $index === false
            ? ''
            : implode('/', array_slice($parts, 0, $index + 2)) . '/';
    }

    /**
     * @param string $file A system's file.
     *
     * @return string Its layer: the folder after the system's.
     */
    private function layer(string $file): string
    {
        $parts = explode('/', $file);
        $index = array_search('src', $parts, true);

        return $index === false ? '' : ($parts[$index + 2] ?? '');
    }
}
