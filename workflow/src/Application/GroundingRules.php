<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Domain\DeclaredEntry;
use AIC\Workflow\Domain\FileChange;
use AIC\Workflow\Domain\ManifestRow;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function array_count_values;
use function array_intersect;
use function array_map;
use function explode;
use function in_array;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strrchr;
use function substr;

/**
 * Grounding's rule 2, real (#118): every File Manifest row is one plain file
 * path with a change AIC knows, a modify or delete row names a file the run
 * starts from, a create row names a path it does not, no path is named twice,
 * and every Entry Points class exists or a create row makes it. The run starts
 * from its base commit (#172), as the code phase's declaration check judges
 * the change, so a run begun on a tree that already holds its work, as after
 * workflow:reset, plans on its spec unchanged; outside git it starts from the
 * tree.
 */
final readonly class GroundingRules
{
    /**
     * The class-like kinds the index records.
     */
    private const array CLASS_LIKE = ['class', 'interface', 'enum', 'trait'];

    /**
     * A row that names more than one file, or a folder.
     */
    private const string NOT_PLAIN = 'row %s is not one plain file path: name each file in its own row.';

    /**
     * A row that leaves the project.
     */
    private const string OUTSIDE = 'row %s is not a path inside the project: write it relative to the root, with no . or .. segment.';

    /**
     * @param CodeLookup $codeLookup The index, rebuilt in full this attempt.
     * @param CodePaths  $codePaths  A path's class and what is on disk.
     * @param RunHost    $runHost    What the run's base commit holds (#172).
     */
    public function __construct(
        private CodeLookup $codeLookup,
        private CodePaths $codePaths,
        private RunHost $runHost,
    ) {
    }

    /**
     * @param list<ManifestRow>   $manifestRows The File Manifest.
     * @param list<DeclaredEntry> $entries      The Entry Points.
     * @param string|null         $baseCommit   The run's base commit; null outside git.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return list<string> Each reason a row or an entry is not real; none when all are.
     */
    public function real(array $manifestRows, array $entries, ?string $baseCommit): array
    {
        $reasons = [];

        foreach ($manifestRows as $manifestRow) {
            $reasons = [...$reasons, ...$this->row($manifestRow, $baseCommit)];
        }

        $paths = array_map(static fn (ManifestRow $manifestRow): string => $manifestRow->path, $manifestRows);

        foreach (array_count_values($paths) as $path => $count) {
            $reasons = $count > 1
                ? [...$reasons, sprintf('row %s appears %d times: keep one.', $path, $count)]
                : $reasons;
        }

        foreach ($entries as $entry) {
            $reasons = [...$reasons, ...$this->entry($entry->class, $manifestRows)];
        }

        return $reasons;
    }

    /**
     * @param string $class A class's full name.
     *
     * @throws WorkflowRefusal When a called boundary reports its documented failure.
     *
     * @return CodeSymbol|null The class-like the index holds under exactly that name; null when none.
     */
    public function existing(string $class): ?CodeSymbol
    {
        foreach ($this->codeLookup->named($class) as $codeSymbol) {
            if ($codeSymbol->name === $class && in_array($codeSymbol->kind, self::CLASS_LIKE, true)) {
                return $codeSymbol;
            }
        }

        return null;
    }

    /**
     * @param list<ManifestRow> $manifestRows The File Manifest.
     *
     * @return list<string> The classes its create rows make, by PSR-4 or the module convention.
     */
    public function created(array $manifestRows): array
    {
        $classes = [];

        foreach ($manifestRows as $manifestRow) {
            $class = $manifestRow->fileChange === FileChange::Create
                ? $this->codePaths->classOf($manifestRow->path)
                : '';
            $classes = $class === ''
                ? $classes
                : [...$classes, $class];
        }

        return $classes;
    }

    /**
     * @param list<ManifestRow> $manifestRows The File Manifest.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return list<string> A note per created class whose short name the index already holds as another class.
     */
    public function twins(array $manifestRows): array
    {
        $notes = [];

        foreach ($this->created($manifestRows) as $class) {
            $notes = [...$notes, ...$this->twinsOf($class)];
        }

        return $notes;
    }

    /**
     * @param string $name A name, perhaps namespaced.
     *
     * @return string Its last segment.
     */
    public function short(string $name): string
    {
        $last = strrchr($name, '\\');

        return $last === false
            ? $name
            : substr($last, 1);
    }

    /**
     * @param string $class A class a create row makes.
     *
     * @throws WorkflowRefusal When a called boundary reports its documented failure.
     *
     * @return list<string> A note per other class the index holds under its short name.
     */
    private function twinsOf(string $class): array
    {
        $notes = [];

        foreach ($this->codeLookup->named($this->short($class)) as $codeSymbol) {
            if ($codeSymbol->name === $class || !in_array($codeSymbol->kind, self::CLASS_LIKE, true)) {
                continue;
            }

            $notes[] = sprintf('%s exists: is %s a second one?', $codeSymbol->name, $class);
        }

        return $notes;
    }

    /**
     * @param ManifestRow $manifestRow A row.
     * @param string|null $baseCommit  The run's base commit; null outside git.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return list<string> Why it is not real; none when it is.
     */
    private function row(ManifestRow $manifestRow, ?string $baseCommit): array
    {
        $path = $manifestRow->path;
        $plain = self::plain($path);

        if ($plain !== '') {
            return [$plain];
        }

        if ($this->codePaths->isDirectory($path)) {
            return [sprintf('row %s is a directory: name each file in its own row.', $path)];
        }

        return match ($manifestRow->fileChange) {
            null => [sprintf('row %s: Change "%s" is not create, modify or delete.', $path, $manifestRow->cell)],
            FileChange::Create => $this->startsWith($path, $baseCommit, true)
                ? [sprintf('row %s (create) is a file the run starts from: write modify.', $path)]
                : [],
            FileChange::Modify, FileChange::Delete => $this->startsWith($path, $baseCommit, false)
                ? []
                : [sprintf('row %s (%s) is not a file the run starts from: check the path, or write create.', $path, $manifestRow->cell)],
        };
    }

    /**
     * @param string            $class        An Entry Points class.
     * @param list<ManifestRow> $manifestRows The File Manifest.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return list<string> Why it is not real; none when it exists or a create row makes it.
     */
    private function entry(string $class, array $manifestRows): array
    {
        $codeSymbol = $this->existing($class);

        if (!$codeSymbol instanceof CodeSymbol) {
            return in_array($class, $this->created($manifestRows), true)
                ? []
                : [sprintf('Entry Points class %s does not exist and no create row makes it.', $class)];
        }

        $deleted = array_any(
            $manifestRows,
            static fn (ManifestRow $manifestRow): bool => $manifestRow->fileChange === FileChange::Delete
                && $manifestRow->path === $codeSymbol->file,
        );

        return $deleted
            ? [sprintf('Entry Points class %s: its file is deleted by row %s.', $class, $codeSymbol->file)]
            : [];
    }

    /**
     * @param string      $path       A row's path.
     * @param string|null $baseCommit The run's base commit; null outside git.
     * @param bool        $onDisk     Without a base, whether a file on disk that git does not list counts.
     *
     * @throws WorkflowRefusal When a called boundary reports its documented failure.
     *
     * @return bool Whether the run starts from that file: the base commit holds it, or, outside git, the tree does.
     */
    private function startsWith(string $path, ?string $baseCommit, bool $onDisk): bool
    {
        if ($baseCommit !== null) {
            return $this->runHost->fileAt($baseCommit, $path) !== null;
        }

        return $this->listed($path) || ($onDisk && $this->codePaths->exists($path));
    }

    /**
     * @param string $path A row's path.
     *
     * @throws WorkflowRefusal When a called boundary reports its documented failure.
     *
     * @return bool Whether the rebuilt index holds it: a file git lists.
     */
    private function listed(string $path): bool
    {
        return in_array($path, $this->codeLookup->filesUnder($path), true);
    }

    /**
     * @param string $path A row's path.
     *
     * @return string Why it is not one plain file path in the project; empty when it is.
     */
    private static function plain(string $path): string
    {
        $segments = explode('/', $path);

        return match (true) {
            preg_match('/[*?\\[\\]{}]/', $path) === 1 || str_ends_with($path, '/') => sprintf(self::NOT_PLAIN, $path),
            str_starts_with($path, '/') || array_intersect(['.', '..'], $segments) !== [] => sprintf(self::OUTSIDE, $path),
            str_starts_with($path, 'var/') => sprintf('row %s is under var/, which only the engine writes.', $path),
            default => '',
        };
    }
}
