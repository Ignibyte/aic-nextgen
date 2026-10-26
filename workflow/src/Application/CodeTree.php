<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeAnswer;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_keys;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function strtolower;

/**
 * The code index's answers (#106): the overview, one area's outline, a
 * search and a symbol's references, each as lines with the files it showed
 * and the scopes it outlined.
 */
final readonly class CodeTree
{
    /**
     * The matches a search shows at most.
     */
    private const int MATCHES = 40;

    /**
     * @param CodeLookup     $codeLookup     Reads the index.
     * @param CodeOutline    $codeOutline    Writes an area's outline.
     * @param CodeModuleView $codeModuleView Writes a module's overview line.
     * @param CodeRefsView   $codeRefsView   Writes a symbol's references.
     * @param CodeLines      $codeLines      Says things.
     */
    public function __construct(
        private CodeLookup $codeLookup,
        private CodeOutline $codeOutline,
        private CodeModuleView $codeModuleView,
        private CodeRefsView $codeRefsView,
        private CodeLines $codeLines,
    ) {
    }

    /**
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return CodeAnswer The systems, the modules, the themes and the rest of the project, with their counts.
     */
    public function overview(): CodeAnswer
    {
        $placed = ['module' => [], 'rest' => [], 'system' => [], 'theme' => []];

        foreach ($this->codeLookup->areas() as $area => $counts) {
            [$bucket, $line] = $this->placed($area, $counts);
            $placed[$bucket][] = $line;
        }

        $themes = $placed['theme'] === []
            ? []
            : ['themes:', ...$placed['theme']];

        $lines = ['systems:', ...$placed['system'], 'modules:', ...$placed['module'], ...$themes, ...$placed['rest']];

        return new CodeAnswer($lines, [], ['*']);
    }

    /**
     * @param string $target A module, a system, an area's key (`theme:app`, #157) or a path.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return CodeAnswer Its outline; not found when nothing has that name.
     */
    public function area(string $target): CodeAnswer
    {
        $areas = array_keys($this->codeLookup->areas());

        foreach ($areas as $area) {
            if (
                $area === $target
                || $area === 'module:' . $target
                || strtolower($area) === 'system:' . strtolower($target)
            ) {
                return $this->codeOutline->area($area);
            }
        }

        return $this->codeLookup->filesUnder($target) === []
            ? new CodeAnswer(
                ['code:tree — nothing in the index is called ' . $target . '; name a module, system, theme:<name> or path.'],
                [],
                [],
                false,
            )
            : $this->codeOutline->path($target);
    }

    /**
     * @param string $text What a name contains.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return CodeAnswer The matches, 40 at most.
     */
    public function search(string $text): CodeAnswer
    {
        $matches = $this->codeLookup->search($text, self::MATCHES + 1);
        $lines = [];

        foreach (array_slice($matches, 0, self::MATCHES) as $codeSymbol) {
            $lines[] = $this->codeLines->symbol($codeSymbol);
        }

        $lines = $matches === []
            ? ['code:search — nothing in the index contains "' . $text . '".']
            : $lines;
        $lines = count($matches) > self::MATCHES
            ? [...$lines, '… more than ' . self::MATCHES . ' match: narrow the text.']
            : $lines;

        return new CodeAnswer($lines, $this->files(array_slice($matches, 0, self::MATCHES)), []);
    }

    /**
     * @param string $name A full name or a short one.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return CodeAnswer What refers to it and what it refers to; its candidates when the name is ambiguous.
     */
    public function refs(string $name): CodeAnswer
    {
        $candidates = $this->codeLookup->named($name);

        return match (count($candidates)) {
            0 => new CodeAnswer(['code:refs — the index has no symbol named ' . $name . '.'], [], [], false),
            1 => $this->codeRefsView->refs($candidates[0]),
            default => new CodeAnswer(
                [
                    'code:refs — ' . $name . ' names ' . count($candidates) . ' symbols; ask for one by its full name:',
                    ...$this->candidateLines($candidates),
                ],
                $this->files($candidates),
                [],
            ),
        };
    }

    /**
     * @param string                          $area   An area.
     * @param array{files: int, symbols: int} $counts Its counts.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return array{'module'|'rest'|'system'|'theme', string} Where its overview line goes, and the line.
     */
    private function placed(string $area, array $counts): array
    {
        $parts = explode(':', $area, 2);
        $kind = $parts[0];
        $name = $parts[1] ?? '';

        return match ($kind) {
            'system' => ['system', '  ' . $name . ' — ' . $this->counted($counts) . $this->codeOutline->layers($area)],
            'module' => ['module', $this->codeModuleView->line($name)],
            'theme' => ['theme', '  ' . $name . ' (theme:' . $name . ') — ' . $this->counted($counts)],
            default => ['rest', $area . ' — ' . $this->counted($counts)],
        };
    }

    /**
     * @param list<CodeSymbol> $candidates Symbols.
     *
     * @return list<string> One line each.
     */
    private function candidateLines(array $candidates): array
    {
        $lines = [];

        foreach ($candidates as $candidate) {
            $lines[] = '  ' . $this->codeLines->symbol($candidate);
        }

        return $lines;
    }

    /**
     * @param array{files: int, symbols: int} $counts An area's counts.
     *
     * @return string Them in words.
     */
    private function counted(array $counts): string
    {
        return $counts['symbols'] . ' symbols in ' . $counts['files'] . ' files';
    }

    /**
     * @param list<CodeSymbol> $symbols Symbols.
     *
     * @return list<string> Their files, once each.
     */
    private function files(array $symbols): array
    {
        $files = [];

        foreach ($symbols as $symbol) {
            $files[] = $symbol->file;
        }

        return array_values(array_unique(array_filter($files, static fn (string $file): bool => $file !== '')));
    }
}
