<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use AIC\Workflow\Application\Console\ConsoleOutput;
use AIC\Workflow\Domain\CoverageSurface;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * How `code:coverage` answers (#232): a count line, then each group's
 * surfaces in columns with the first app file that uses each, or `unused`;
 * or the `aic.console/1` envelope's data. `--missing` keeps only the unused.
 */
final readonly class CoverageLines
{
    /**
     * The command.
     */
    private const string COMMAND = 'code:coverage';

    /**
     * @param ConsoleOutput $consoleOutput Columns and the envelope.
     */
    public function __construct(
        private ConsoleOutput $consoleOutput = new ConsoleOutput(),
    ) {
    }

    /**
     * @param list<CoverageSurface> $surfaces Every surface.
     * @param bool                  $missing  Whether to list only the unused ones.
     *
     * @return list<string> The text answer.
     */
    public function text(array $surfaces, bool $missing): array
    {
        $lines = [self::count($surfaces)];

        foreach (self::groups($surfaces) as $group => $members) {
            $used = count(array_filter($members, static fn (CoverageSurface $coverageSurface): bool => $coverageSurface->used()));
            $shown = $missing
                ? self::unused($members)
                : $members;
            $lines = [
                ...$lines,
                '',
                $group . ' — ' . $used . ' of ' . count($members) . ' used',
                ...$this->consoleOutput->table(['SURFACE', 'USED BY'], array_map(self::row(...), $shown), '(every one used)'),
            ];
        }

        return $lines;
    }

    /**
     * @param list<CoverageSurface> $surfaces Every surface.
     * @param bool                  $missing  Whether to list only the unused ones.
     *
     * @return string The envelope, one line.
     */
    public function json(array $surfaces, bool $missing): string
    {
        $shown = $missing
            ? self::unused($surfaces)
            : $surfaces;
        $used = count(array_filter($surfaces, static fn (CoverageSurface $coverageSurface): bool => $coverageSurface->used()));
        $data = json_encode([
            'surfaces' => array_map(static fn (CoverageSurface $coverageSurface): array => [
                'files' => $coverageSurface->files,
                'group' => $coverageSurface->group,
                'name' => $coverageSurface->name,
                'rendered' => $coverageSurface->rendered,
                'used' => $coverageSurface->used(),
            ], $shown),
            'total' => count($surfaces),
            'unused' => count($surfaces) - $used,
            'used' => $used,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->consoleOutput->envelope(self::COMMAND, $data, []);
    }

    /**
     * @param list<CoverageSurface> $surfaces Every surface.
     *
     * @return string The count line.
     */
    private static function count(array $surfaces): string
    {
        $used = count(array_filter($surfaces, static fn (CoverageSurface $coverageSurface): bool => $coverageSurface->used()));

        return self::COMMAND . ' — ' . count($surfaces) . ' surfaces: ' . $used . ' used, ' . (count($surfaces) - $used) . ' unused.';
    }

    /**
     * @param list<CoverageSurface> $surfaces Surfaces.
     *
     * @return array<string, list<CoverageSurface>> They by group, in their order.
     */
    private static function groups(array $surfaces): array
    {
        $groups = [];

        foreach ($surfaces as $surface) {
            $groups[$surface->group][] = $surface;
        }

        return $groups;
    }

    /**
     * @param list<CoverageSurface> $surfaces Surfaces.
     *
     * @return list<CoverageSurface> The unused ones.
     */
    private static function unused(array $surfaces): array
    {
        return array_values(array_filter($surfaces, static fn (CoverageSurface $coverageSurface): bool => !$coverageSurface->used()));
    }

    /**
     * @param CoverageSurface $coverageSurface A surface.
     *
     * @return list<string> Its row: its name, and its first file and how many more, AIC's rendering, or `unused`.
     */
    private static function row(CoverageSurface $coverageSurface): array
    {
        $files = $coverageSurface->files;
        $first = $files === []
            ? ''
            : $files[0] . (count($files) > 1 ? ' (+' . (count($files) - 1) . ')' : '');
        $rendered = $coverageSurface->rendered
            ? 'rendered by AIC'
            : '';
        $cell = match (true) {
            $first !== '' && $rendered !== '' => $rendered . '; ' . $first,
            $first !== '' => $first,
            $rendered !== '' => $rendered,
            default => 'unused',
        };

        return [$coverageSurface->name, $cell];
    }
}
