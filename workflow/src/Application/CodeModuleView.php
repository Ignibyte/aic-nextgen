<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function count;
use function in_array;

/**
 * A module as the code index tells it (#106): its overview line (namespace,
 * classes, requires, dependents, routes by kind) and its surface in an
 * outline (routes, commands and jobs with their handlers, exports, requires,
 * dependents).
 */
final readonly class CodeModuleView
{
    /**
     * The kinds that are class-likes.
     */
    private const array CLASSES = ['class', 'interface', 'trait', 'enum'];

    /**
     * @param CodeLookup $codeLookup Reads the index.
     * @param CodeLines  $codeLines  Says things.
     */
    public function __construct(
        private CodeLookup $codeLookup,
        private CodeLines $codeLines,
    ) {
    }

    /**
     * @param string $module A module's name.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return string Its overview line.
     */
    public function line(string $module): string
    {
        $name = 'module:' . $module;
        $symbols = $this->codeLookup->inArea($name);
        $classes = count(array_filter(
            $symbols,
            static fn (CodeSymbol $codeSymbol): bool => in_array($codeSymbol->kind, self::CLASSES, true),
        ));
        $namespace = $this->namespace($symbols);

        return '  ' . $module . ($namespace === '' ? '' : ' (' . $namespace . ')') . ' — ' . $classes . ' classes; requires '
            . $this->codeLines->joined($this->codeLines->ends($this->codeLookup->outgoing($name), 'requires', false)) . '; required by '
            . $this->codeLines->joined($this->codeLines->ends($this->codeLookup->incoming($name), 'requires', true)) . '; routes '
            . $this->codeLines->joined($this->routeCounts($symbols));
    }

    /**
     * @param string           $module  A module's name.
     * @param list<CodeSymbol> $symbols Its symbols.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<string> Its surface's lines.
     */
    public function surface(string $module, array $symbols): array
    {
        $name = 'module:' . $module;
        $lines = [];

        foreach ($symbols as $symbol) {
            if (!in_array($symbol->kind, ['route', 'command', 'job'], true)) {
                continue;
            }

            $lines[] = '  ' . $this->entry($symbol);
        }

        return [
            ...$lines,
            '  exports ' . $this->codeLines->joined($this->codeLines->ends($this->codeLookup->outgoing($name), 'exports', false)),
            '  requires ' . $this->codeLines->joined($this->codeLines->ends($this->codeLookup->outgoing($name), 'requires', false)),
            '  required by ' . $this->codeLines->joined($this->codeLines->ends($this->codeLookup->incoming($name), 'requires', true)),
        ];
    }

    /**
     * @param CodeSymbol $codeSymbol A route, command or job.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return string It with its kind and the class that handles it.
     */
    private function entry(CodeSymbol $codeSymbol): string
    {
        $outgoing = $this->codeLookup->outgoing($codeSymbol->name);
        $handlers = [...$this->codeLines->ends($outgoing, 'routes_to', false), ...$this->codeLines->ends($outgoing, 'handles', false)];

        return $codeSymbol->name . ' [' . $codeSymbol->detail . '] → ' . $this->codeLines->joined($handlers);
    }

    /**
     * @param list<CodeSymbol> $symbols A module's symbols.
     *
     * @return string Its namespace; empty when the surface does not list it.
     */
    private function namespace(array $symbols): string
    {
        foreach ($symbols as $symbol) {
            if ($symbol->kind === 'module') {
                return $symbol->detail;
            }
        }

        return '';
    }

    /**
     * @param list<CodeSymbol> $symbols A module's symbols.
     *
     * @return list<string> Its routes counted by kind, such as `Page 4`.
     */
    private function routeCounts(array $symbols): array
    {
        $counts = [];

        foreach ($symbols as $symbol) {
            if ($symbol->kind !== 'route') {
                continue;
            }

            $counts[$symbol->detail] = ($counts[$symbol->detail] ?? 0) + 1;
        }

        $named = [];

        foreach ($counts as $kind => $count) {
            $named[] = $kind . ' ' . $count;
        }

        return $named;
    }
}
