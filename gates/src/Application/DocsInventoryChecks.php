<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\MarkdownDocument;
use Gates\Domain\RegexCaptures;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function count;
use function explode;
use function in_array;
use function preg_match;
use function sprintf;

/**
 * gate:29's inventory checks: the README links every page (D1), `gates.md`
 * lists the gates composer.json runs and CONSTITUTION §0 rows (D2), and every
 * namespace under `framework/src/` sits in exactly one system section of
 * `systems.md` (D4).
 */
final readonly class DocsInventoryChecks
{
    use RegexCaptures;

    /**
     * @param MarkdownDocument $markdownDocument The README.
     * @param list<string>     $pages            Every page's file name.
     *
     * @return list<string> A finding per page the README does not link.
     */
    public function unlinked(MarkdownDocument $markdownDocument, array $pages): array
    {
        $linked = [];

        foreach ($markdownDocument->links() as [, $target]) {
            $linked[] = explode('#', $target, 2)[0];
        }

        $findings = [];

        foreach (array_diff($pages, $linked, ['README.md']) as $page) {
            $findings[] = sprintf('README.md: %s is not linked; add it to the reading list.', $page);
        }

        return $findings;
    }

    /**
     * @param MarkdownDocument $markdownDocument `gates.md`.
     * @param list<int>        $scripts          The gate numbers composer.json runs.
     * @param list<int>        $rows             The gate numbers CONSTITUTION §0 lists.
     *
     * @return list<string> A finding per gate one list has and another lacks.
     */
    public function gates(MarkdownDocument $markdownDocument, array $scripts, array $rows): array
    {
        $listed = [];

        foreach ($markdownDocument->firstCells() as [, $cell]) {
            if (preg_match('/^\\d+$/', $cell) !== 1) {
                continue;
            }

            $listed[] = (int) $cell;
        }

        return [
            ...self::differ($scripts, $listed, 'gates.md: gate:%d runs in composer.json but has no row; add one.'),
            ...self::differ($listed, $scripts, 'gates.md: gate:%d has a row but no composer.json script; drop the row.'),
            ...self::differ($scripts, $rows, 'CONSTITUTION.md: gate:%d runs in composer.json but §0 has no row; add one.'),
            ...self::differ($rows, $scripts, 'CONSTITUTION.md: §0 lists gate:%d, which composer.json does not run.'),
        ];
    }

    /**
     * @param MarkdownDocument $markdownDocument `systems.md`.
     * @param list<string>     $folders          The namespace folders under `framework/src/`.
     *
     * @return list<string> A finding per namespace named in no system section, in two, or with no folder.
     */
    public function namespaces(MarkdownDocument $markdownDocument, array $folders): array
    {
        $named = self::named($markdownDocument);
        $findings = [];

        foreach ($folders as $folder) {
            $count = $named[$folder] ?? 0;

            if ($count === 1) {
                continue;
            }

            $findings[] = sprintf('systems.md: AIC\\%s is named in %d system sections; name it in exactly one.', $folder, $count);
        }

        foreach (array_diff(array_keys($named), $folders) as $namespace) {
            $findings[] = sprintf('systems.md: AIC\\%s has no folder under framework/src/; drop it or fix the name.', $namespace);
        }

        return $findings;
    }

    /**
     * @param MarkdownDocument $markdownDocument `systems.md`.
     *
     * @return array<string, int> How many system sections name each top-level namespace.
     */
    private static function named(MarkdownDocument $markdownDocument): array
    {
        $sections = self::sections($markdownDocument);
        $seen = [];

        foreach ($markdownDocument->code() as [$line, $span]) {
            $match = self::regexMatch('/^AIC\\\\(\\w+)$/', $span);

            if (!array_key_exists($line, $sections) || !array_key_exists(1, $match)) {
                continue;
            }

            $seen[$match[1]][$sections[$line]] = true;
        }

        $named = [];

        foreach ($seen as $namespace => $inSections) {
            $named[$namespace] = count($inSections);
        }

        return $named;
    }

    /**
     * @param MarkdownDocument $markdownDocument `systems.md`.
     *
     * @return array<int, int> For each line inside a system section (`## <n>. <Name>`), the section's heading line.
     */
    private static function sections(MarkdownDocument $markdownDocument): array
    {
        $starts = self::starts($markdownDocument);
        $sections = [];
        $current = null;

        foreach (array_keys($markdownDocument->lines()) as $line) {
            $current = self::sectionAt($starts, $line, $current);

            if ($current === null) {
                continue;
            }

            $sections[$line] = $current;
        }

        return $sections;
    }

    /**
     * @param MarkdownDocument $markdownDocument `systems.md`.
     *
     * @return array<int, bool> Every top-level heading's line, and whether it opens a system section.
     */
    private static function starts(MarkdownDocument $markdownDocument): array
    {
        $starts = [];

        foreach ($markdownDocument->headings() as [$line, $level, $text]) {
            if ($level > 2) {
                continue;
            }

            $starts[$line] = $level === 2 && preg_match('/^\\d+\\.\\s/', $text) === 1;
        }

        return $starts;
    }

    /**
     * @param array<int, bool> $starts  The top-level headings, as `starts()` gives them.
     * @param int              $line    A line.
     * @param int|null         $current The system section the line before sits in.
     *
     * @return int|null The system section this line sits in.
     */
    private static function sectionAt(array $starts, int $line, ?int $current): ?int
    {
        if (!array_key_exists($line, $starts)) {
            return $current;
        }

        return $starts[$line]
            ? $line
            : null;
    }

    /**
     * @param list<int> $have   What one list holds.
     * @param list<int> $lacks  What the other holds.
     * @param string    $format The finding, with `%d` for the gate.
     *
     * @return list<string> A finding per number in the first list and not the second.
     */
    private static function differ(array $have, array $lacks, string $format): array
    {
        $findings = [];

        foreach ($have as $number) {
            if (in_array($number, $lacks, true)) {
                continue;
            }

            $findings[] = sprintf($format, $number);
        }

        return $findings;
    }
}
