<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\DeclaredEntry;
use AIC\Workflow\Domain\FileChange;
use AIC\Workflow\Domain\ManifestRow;

use function array_map;
use function array_slice;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * A run's spec as a contract (#101, Droost's `SpecContract`): the sections the
 * engine reads and freezes, in AIC's pipeline-spec form. Criteria are
 * `| # | Target | Met |`, the manifest `| File | Change | Purpose |` with one
 * plain path per row, and entry points
 * `| Kind | Method | Path or name | Class | Module | Status | Example |`.
 */
final readonly class SpecContract
{
    /**
     * The criteria's heading.
     */
    public const string CRITERIA = '## Acceptance Criteria';

    /**
     * The file manifest's heading.
     */
    public const string MANIFEST = '### File Manifest';

    /**
     * The entry points' heading.
     */
    public const string ENTRIES = '## Entry Points';

    /**
     * The decisions' heading.
     */
    public const string DECISIONS = '### Locked-In Decisions';

    /**
     * The browser check's heading.
     */
    public const string BROWSER = '## Browser Check';

    /**
     * The five kinds an entry point may be.
     */
    private const array KINDS = ['Page', 'Api', 'Stream', 'Command', 'Job'];

    /**
     * @param MarkdownSections $markdownSections The spec's sections.
     * @param string           $text             The spec's text, for its frontmatter.
     */
    public function __construct(
        private MarkdownSections $markdownSections,
        private string $text,
    ) {
    }

    /**
     * @return list<string> What keeps it from being a contract: a section missing or malformed. Empty when it is one.
     */
    public function problems(): array
    {
        $problems = [];

        if ($this->criteria() === []) {
            $problems[] = self::CRITERIA . ' holds no `| # | Target | Met |` row.';
        }

        if ($this->files() === []) {
            $problems[] = self::MANIFEST . ' holds no `| File | Change | Purpose |` row.';
        }

        $entries = $this->markdownSections->rows(self::ENTRIES);

        if ($entries === null) {
            $problems[] = self::ENTRIES . ' is missing; write it with no rows when the work adds no entry point.';
        }

        foreach ($entries ?? [] as $row) {
            $problems = [...$problems, ...$this->entryProblems($row)];
        }

        return $problems;
    }

    /**
     * @return list<array{string, string}> Each criterion's number and its `Met` cell.
     */
    public function criteria(): array
    {
        $criteria = [];

        foreach ($this->markdownSections->rows(self::CRITERIA) ?? [] as $row) {
            if (count($row) < 3 || !ctype_digit($row[0])) {
                continue;
            }

            $criteria[] = [$row[0], $row[count($row) - 1]];
        }

        return $criteria;
    }

    /**
     * @return list<string> The paths the manifest declares, one per row, without backticks.
     */
    public function files(): array
    {
        return array_map(static fn (ManifestRow $manifestRow): string => $manifestRow->path, $this->manifestRows());
    }

    /**
     * @return list<ManifestRow> Each `### File Manifest` row with a path: the path, its change (#118) and the cell as written.
     */
    public function manifestRows(): array
    {
        $rows = [];

        foreach ($this->markdownSections->rows(self::MANIFEST) ?? [] as $row) {
            $path = trim($row[0] ?? '', ' `');

            if ($path === '') {
                continue;
            }

            $cell = trim($row[1] ?? '', ' `');
            $rows[] = new ManifestRow($path, self::change($cell), $cell);
        }

        return $rows;
    }

    /**
     * @return list<DeclaredEntry> The entry points it declares.
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->markdownSections->rows(self::ENTRIES) ?? [] as $row) {
            $cells = array_map(static fn (string $cell): string => trim($cell, ' `'), $row);

            if (count($cells) !== 7 || $this->entryProblems($row) !== []) {
                continue;
            }

            $entries[] = $this->entry([$cells[0], $cells[1], $cells[2], $cells[3], $cells[4], $cells[5], $cells[6]]);
        }

        return $entries;
    }

    /**
     * @return array<string, list<string>> What the freeze holds, by section: each row or item as one normalized line,
     *                                     criteria without their `Met` cell, browser items without tick or `saw:`.
     */
    public function frozenRows(): array
    {
        $criteria = [];

        foreach ($this->markdownSections->rows(self::CRITERIA) ?? [] as $row) {
            $criteria[] = implode(' | ', array_slice($row, 0, -1));
        }

        return [
            self::BROWSER => array_map(
                static fn (string $item): string => trim((string) preg_replace('/^\\[[ xX]\\]\\s*|\\s*(?:—|-)?\\s*saw:.*$/u', '', $item)),
                $this->markdownSections->items(self::BROWSER),
            ),
            self::CRITERIA => $criteria,
            self::DECISIONS => $this->markdownSections->items(self::DECISIONS),
            self::ENTRIES => $this->joined(self::ENTRIES),
            self::MANIFEST => $this->joined(self::MANIFEST),
        ];
    }

    /**
     * @return array{int, int} How many browser check items are ticked, and how many there are.
     */
    public function browserTicks(): array
    {
        $items = $this->markdownSections->items(self::BROWSER);
        $ticked = 0;

        foreach ($items as $item) {
            $ticked += preg_match('/^\\[[xX]\\]/', $item) === 1
                ? 1
                : 0;
        }

        return [$ticked, count($items)];
    }

    /**
     * @return bool Whether the frontmatter says `browser_testable: Yes`.
     */
    public function browserTestable(): bool
    {
        return preg_match('/^browser_testable:\\s*Yes\\s*$/mi', $this->text) === 1;
    }

    /**
     * @param string $heading A table's heading.
     *
     * @return list<string> Its rows, each as one line of cells.
     */
    private function joined(string $heading): array
    {
        return array_map(static fn (array $row): string => implode(' | ', $row), $this->markdownSections->rows($heading) ?? []);
    }

    /**
     * @param list<string> $row An entry-point row.
     *
     * @return list<string> What is wrong with it.
     */
    private function entryProblems(array $row): array
    {
        $line = implode(' | ', $row);

        if (count($row) !== 7) {
            return [self::ENTRIES . ' row "' . $line . '" has ' . count($row) . ' cells, not 7.'];
        }

        if (!in_array(trim($row[0], ' `'), self::KINDS, true)) {
            return [self::ENTRIES . ' row "' . $line . '" names no kind of Page, Api, Stream, Command or Job.'];
        }

        $route = in_array(trim($row[0], ' `'), ['Page', 'Api', 'Stream'], true);

        return $route === (trim($row[1], ' `') !== '') && trim($row[2], ' `') !== '' && trim($row[3], ' `') !== ''
            ? []
            : [self::ENTRIES . ' row "' . $line . '": a route needs a method and a command or job none; each needs a name and a class.'];
    }

    /**
     * @param array{string, string, string, string, string, string, string} $cells A valid entry-point row's cells, without backticks.
     *
     * @return DeclaredEntry The entry.
     */
    private function entry(array $cells): DeclaredEntry
    {
        $route = $cells[1] !== '';

        return new DeclaredEntry(
            $cells[0],
            $cells[1],
            $cells[2],
            $cells[3],
            $route ? $cells[4] : '',
            $route && ctype_digit($cells[5]) ? (int) $cells[5] : 200,
            $route ? $cells[6] : '',
        );
    }

    /**
     * @param string $cell A `Change` cell.
     *
     * @return FileChange|null Its first word as a change; null when it is none.
     */
    private static function change(string $cell): ?FileChange
    {
        return match (strtolower(explode(' ', $cell)[0])) {
            'create' => FileChange::Create,
            'modify' => FileChange::Modify,
            'delete' => FileChange::Delete,
            default => null,
        };
    }
}
