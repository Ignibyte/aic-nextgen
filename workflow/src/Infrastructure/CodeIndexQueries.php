<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\CodeLookup;
use AIC\Workflow\Domain\CodeCrossing;
use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function addcslashes;
use function ksort;

/**
 * The code index over SQLite (#106, droost-workflow's `CodeIndex` matching
 * rules): a name is the whole name or its last segment, a search is
 * case-insensitive, a path matches from its start. Every argument reaches
 * SQL bound, `LIKE` patterns escaped.
 */
final readonly class CodeIndexQueries implements CodeLookup
{
    /**
     * A symbol row's columns.
     */
    private const string SYMBOL = CodeIndexRows::SYMBOL;

    /**
     * An edge row's columns, call chains left out: they are the coverage report's alone (#232).
     */
    private const string EDGE = CodeIndexRows::EDGE . " WHERE kind <> 'chains' AND";

    /**
     * Each edge whose source file's area and whose class-like target's area differ, one end in the asked area (#108).
     */
    private const string CROSSING = <<<'SQL'
        SELECT DISTINCT t.name AS target, f.area AS source_area, t.area AS target_area
        FROM edge e
        JOIN file f ON f.path = e.file
        JOIN symbol t ON t.name = CASE WHEN instr(e.target, '::') > 0 THEN substr(e.target, 1, instr(e.target, '::') - 1) ELSE e.target END
            AND t.kind IN ('class', 'interface', 'enum', 'trait')
        WHERE e.kind <> 'chains' AND f.area <> t.area AND (f.area = ? OR t.area = ?)
        ORDER BY source_area, target_area, target
        SQL;

    /**
     * @param CodeIndexRows $codeIndexRows Reads its rows.
     */
    public function __construct(
        private CodeIndexRows $codeIndexRows,
    ) {
    }

    /**
     * @return array<string, array{files: int, symbols: int}> Each area's counts.
     */
    #[Override]
    public function areas(): array
    {
        $areas = [];

        foreach ($this->codeIndexRows->rows('SELECT area, COUNT(*) AS files FROM file GROUP BY area', []) as $jsonReader) {
            $areas[$jsonReader->string('area')] = ['files' => $jsonReader->int('files'), 'symbols' => 0];
        }

        $symbols = "SELECT area, COUNT(*) AS symbols FROM symbol WHERE kind <> 'method' GROUP BY area";

        foreach ($this->codeIndexRows->rows($symbols, []) as $jsonReader) {
            $area = $jsonReader->string('area');
            $areas[$area] = ['files' => $areas[$area]['files'] ?? 0, 'symbols' => $jsonReader->int('symbols')];
        }

        ksort($areas);

        return $areas;
    }

    /**
     * @param string $area An area.
     *
     * @return list<CodeSymbol> Its symbols.
     */
    #[Override]
    public function inArea(string $area): array
    {
        return $this->codeIndexRows->symbols(self::SYMBOL . ' WHERE area = ? ORDER BY file, line, name', [$area]);
    }

    /**
     * @param string $prefix A path's start.
     *
     * @return list<string> The files under it.
     */
    #[Override]
    public function filesUnder(string $prefix): array
    {
        $files = [];

        $rows = $this->codeIndexRows->rows(
            "SELECT path FROM file WHERE path = ? OR path LIKE ? ESCAPE '\\' ORDER BY path",
            [$prefix, $this->like($prefix, false)],
        );

        foreach ($rows as $row) {
            $files[] = $row->string('path');
        }

        return $files;
    }

    /**
     * @param string $prefix A path's start.
     *
     * @return list<CodeSymbol> The symbols in the files under it.
     */
    #[Override]
    public function symbolsUnder(string $prefix): array
    {
        return $this->codeIndexRows->symbols(
            self::SYMBOL . " WHERE file = ? OR file LIKE ? ESCAPE '\\' ORDER BY file, line, name",
            [$prefix, $this->like($prefix, false)],
        );
    }

    /**
     * @param string $text  What a name contains.
     * @param int    $limit How many at most.
     *
     * @return list<CodeSymbol> The matches.
     */
    #[Override]
    public function search(string $text, int $limit): array
    {
        return $this->codeIndexRows->symbols(
            self::SYMBOL . " WHERE name LIKE ? ESCAPE '\\' ORDER BY origin = 'surface', name COLLATE NOCASE LIMIT ?",
            [$this->like($text, true), $limit],
        );
    }

    /**
     * @param string $name A full name or a last segment.
     *
     * @return list<CodeSymbol> The matches.
     */
    #[Override]
    public function named(string $name): array
    {
        $exact = $this->codeIndexRows->symbols(self::SYMBOL . ' WHERE name = ?', [$name]);

        return $exact !== []
            ? $exact
            : $this->codeIndexRows->symbols(self::SYMBOL . " WHERE short = ? COLLATE NOCASE AND kind <> 'method' ORDER BY name", [$name]);
    }

    /**
     * @param string $name A full name.
     *
     * @return list<CodeEdge> The edges naming it or its methods.
     */
    #[Override]
    public function incoming(string $name): array
    {
        return $this->codeIndexRows->edges(
            self::EDGE . " (target = ? OR target LIKE ? ESCAPE '\\') ORDER BY kind, source",
            [$name, $this->like($name . '::', false)],
        );
    }

    /**
     * @param string $name A full name.
     *
     * @return list<CodeEdge> The edges starting at it or its methods.
     */
    #[Override]
    public function outgoing(string $name): array
    {
        return $this->codeIndexRows->edges(
            self::EDGE . " (source = ? OR source LIKE ? ESCAPE '\\') ORDER BY kind, target",
            [$name, $this->like($name . '::', false)],
        );
    }

    /**
     * @param string $area An area.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeCrossing> Each class-like named across the area's edge, once per naming area.
     */
    #[Override]
    public function crossing(string $area): array
    {
        $crossings = [];

        foreach ($this->codeIndexRows->rows(self::CROSSING, [$area, $area]) as $jsonReader) {
            $target = $jsonReader->string('target');
            $crossings[] = new CodeCrossing($target, $jsonReader->string('source_area'), $jsonReader->string('target_area'));
        }

        return $crossings;
    }

    /**
     * @param string $text     What to match.
     * @param bool   $anywhere Whether it may sit anywhere in the value, or only at its start.
     *
     * @return string A `LIKE` pattern with `%`, `_` and `\` escaped.
     */
    private function like(string $text, bool $anywhere): string
    {
        $escaped = addcslashes($text, '%_\\');

        return ($anywhere ? '%' : '') . $escaped . '%';
    }
}
