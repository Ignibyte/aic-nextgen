<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeQuery;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Domain\DeclaredEntry;
use AIC\Workflow\Domain\FileChange;
use AIC\Workflow\Domain\ManifestRow;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function explode;
use function in_array;
use function min;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Grounding's rules 1, 3 and 4 (#118), over this run's lookups at plan:
 * the agent outlined the code at least once, looked up every file it
 * modifies or deletes (its area, when the file is already gone from the
 * tree, #172) and every existing class it names, outlined the area
 * of every file it creates, and searched for an existing class before
 * creating one. A failed lookup counts for nothing but its mention.
 */
final readonly class GroundingLooks
{
    /**
     * @param CodeLookup     $codeLookup     The rebuilt index: which areas and folders hold files yet.
     * @param CodePaths      $codePaths      A path's area and class.
     * @param GroundingRules $groundingRules Finds an existing class, and a class's short name.
     */
    public function __construct(
        private CodeLookup $codeLookup,
        private CodePaths $codePaths,
        private GroundingRules $groundingRules,
    ) {
    }

    /**
     * @param list<CodeQuery>     $codeQueries  This run's lookups at plan, oldest first.
     * @param list<ManifestRow>   $manifestRows The File Manifest.
     * @param list<DeclaredEntry> $entries      The Entry Points.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return list<string> Each reason the plan has not looked; none when it has.
     */
    public function looks(array $codeQueries, array $manifestRows, array $entries): array
    {
        $answered = array_values(array_filter($codeQueries, static fn (CodeQuery $codeQuery): bool => $codeQuery->answered));
        $files = [];
        $scopes = [];

        foreach ($answered as $codeQuery) {
            $files = [...$files, ...$codeQuery->files];
            $scopes = [...$scopes, ...$codeQuery->scopes];
        }

        $reasons = [
            ...self::asked($codeQueries),
            ...$this->rows($manifestRows, $files, $scopes),
            ...$this->entries($entries, $files),
            ...$this->searched($manifestRows, $answered),
        ];

        return $reasons === []
            ? []
            : [...$reasons, ...self::cut($answered)];
    }

    /**
     * @param list<ManifestRow> $manifestRows The File Manifest.
     * @param list<string>      $files        The files the answered lookups printed.
     * @param list<string>      $scopes       The areas they outlined.
     *
     * @return list<string> Rule 3 for rows: each one not looked up, with the lookup that would show it.
     */
    private function rows(array $manifestRows, array $files, array $scopes): array
    {
        $reasons = [];

        foreach ($manifestRows as $manifestRow) {
            if (
                !$manifestRow->fileChange instanceof FileChange
                || $this->shown($manifestRow, $manifestRow->fileChange, $files, $scopes)
            ) {
                continue;
            }

            $reasons[] = sprintf(
                'row %s (%s) was not looked up: run bin/aic code:tree %s.',
                $manifestRow->path,
                $manifestRow->fileChange->value,
                $this->lookup($manifestRow->path),
            );
        }

        return $reasons;
    }

    /**
     * @param ManifestRow  $manifestRow A row.
     * @param FileChange   $fileChange  What it does.
     * @param list<string> $files       The files the answered lookups printed.
     * @param list<string> $scopes      The areas they outlined.
     *
     * @return bool Whether a lookup showed it: the file itself for a modify or delete, its area for a create. A
     *              modify or delete row whose file is already gone from the tree, as after workflow:reset kept an
     *              earlier run's work (#172), cannot be shown; its area or folder stands for it.
     */
    private function shown(ManifestRow $manifestRow, FileChange $fileChange, array $files, array $scopes): bool
    {
        $path = $manifestRow->path;

        return $fileChange->needsFile() && $this->codePaths->exists($path)
            ? in_array($path, $files, true)
            : $this->covered($path, $scopes);
    }

    /**
     * @param list<DeclaredEntry> $entries The Entry Points.
     * @param list<string>        $files   The files the answered lookups printed.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return list<string> Rule 3 for classes: each existing one whose file no lookup printed.
     */
    private function entries(array $entries, array $files): array
    {
        $reasons = [];

        foreach ($entries as $entry) {
            $codeSymbol = $this->groundingRules->existing($entry->class);

            if (!($codeSymbol instanceof CodeSymbol) || in_array($codeSymbol->file, $files, true)) {
                continue;
            }

            $class = $entry->class;
            $reasons[] = sprintf('Entry Points class %s was not looked up: run bin/aic code:refs %s.', $class, $class);
        }

        return $reasons;
    }

    /**
     * @param list<ManifestRow> $manifestRows The File Manifest.
     * @param list<CodeQuery>   $answered     The answered lookups.
     *
     * @return list<string> Rule 4: each created class no search looked for first.
     */
    private function searched(array $manifestRows, array $answered): array
    {
        $targets = array_map(
            static fn (CodeQuery $codeQuery): string => $codeQuery->target,
            array_filter($answered, static fn (CodeQuery $codeQuery): bool => $codeQuery->command === 'code:search'),
        );
        $reasons = [];

        foreach ($this->groundingRules->created($manifestRows) as $class) {
            $short = $this->groundingRules->short($class);

            if (array_any($targets, fn (string $target): bool => $this->finds($target, $short))) {
                continue;
            }

            $reasons[] = sprintf('no code:search this run looked for an existing %s: run bin/aic code:search %s and reuse what it finds, '
                . 'or keep the row.', $short, $short);
        }

        return $reasons;
    }

    /**
     * @param string       $path   A created row's path.
     * @param list<string> $scopes The areas the answered lookups outlined.
     *
     * @return bool Whether an outline covers it: its module or system, a path at or above it, or the overview for a new area.
     */
    private function covered(string $path, array $scopes): bool
    {
        $area = $this->codePaths->area($path);

        return in_array($area, $scopes, true)
            || (in_array('*', $scopes, true) && $this->isNew($path, $area))
            || array_any(
                $scopes,
                static fn (string $scope): bool => str_starts_with($scope, 'path:') && self::under($path, substr($scope, 5)),
            );
    }

    /**
     * @param string $path A created row's path.
     * @param string $area Its area.
     *
     * @return bool Whether its module, system or theme, or its top folder elsewhere, holds no indexed file yet.
     */
    private function isNew(string $path, string $area): bool
    {
        return str_starts_with($area, 'module:') || str_starts_with($area, 'system:') || str_starts_with($area, 'theme:')
            ? !array_key_exists($area, $this->codeLookup->areas())
            : $this->codeLookup->filesUnder(explode('/', $path)[0] . '/') === [];
    }

    /**
     * @param string $path A row's path.
     *
     * @return string The `code:tree` target that shows it: its module, its system, or its folder.
     */
    private function lookup(string $path): string
    {
        $area = $this->codePaths->area($path);

        return match (true) {
            str_starts_with($area, 'module:'), str_starts_with($area, 'system:') => substr($area, (int) strpos($area, ':') + 1),
            str_contains($path, '/') => substr($path, 0, (int) strrpos($path, '/') + 1),
            default => $path,
        };
    }

    /**
     * @param string $target A search's text.
     * @param string $short  A new class's short name.
     *
     * @return bool Whether the search's last segment is long enough and inside the name, case-blind.
     */
    private function finds(string $target, string $short): bool
    {
        $last = $this->groundingRules->short($target);

        return strlen($last) >= min(4, strlen($short)) && str_contains(strtolower($short), strtolower($last));
    }

    /**
     * @param list<CodeQuery> $codeQueries This run's lookups at plan.
     *
     * @return list<string> Rule 1: why no outline answered; none when one did.
     */
    private static function asked(array $codeQueries): array
    {
        $trees = array_values(array_filter($codeQueries, static fn (CodeQuery $codeQuery): bool => $codeQuery->command === 'code:tree'));

        if (array_any($trees, static fn (CodeQuery $codeQuery): bool => $codeQuery->answered)) {
            return [];
        }

        $failed = $trees === []
            ? ''
            : sprintf(' (code:tree was called %d× and failed each time)', count($trees));

        return ['no code:tree answered this run at plan' . $failed . ': run bin/aic code:tree, then bin/aic code:tree <module> for each '
            . 'module the plan touches.'];
    }

    /**
     * @param string $path   A path.
     * @param string $prefix An outlined path.
     *
     * @return bool Whether the path is the outlined path, or inside it as a folder.
     */
    private static function under(string $path, string $prefix): bool
    {
        $folder = rtrim($prefix, '/');

        return $path === $folder || str_starts_with($path, $folder . '/');
    }

    /**
     * @param list<CodeQuery> $answered The answered lookups.
     *
     * @return list<string> A note per lookup whose file list was cut, which may explain a miss.
     */
    private static function cut(array $answered): array
    {
        $notes = [];

        foreach ($answered as $codeQuery) {
            if (!$codeQuery->truncated) {
                continue;
            }

            $asked = $codeQuery->command . ' ' . $codeQuery->target;
            $notes[] = sprintf('the answer for %s was cut at 500 files: outline a narrower path.', $asked);
        }

        return $notes;
    }
}
