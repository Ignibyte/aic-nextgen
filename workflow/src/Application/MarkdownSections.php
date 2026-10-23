<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\RegexCaptures;

use function array_key_exists;
use function array_map;
use function array_search;
use function array_slice;
use function explode;
use function preg_split;
use function str_replace;
use function str_starts_with;
use function trim;

/**
 * Reads a markdown document's sections (#101): the lines under a heading up to
 * the next heading, its table's rows as cells, its list items.
 */
final readonly class MarkdownSections
{
    use RegexCaptures;

    /**
     * @param string $text The document.
     */
    public function __construct(
        private string $text,
    ) {
    }

    /**
     * @param string $heading A heading line, such as `## Acceptance Criteria`.
     *
     * @return list<string>|null The section's lines, without the heading; null when there is no such heading.
     */
    public function lines(string $heading): ?array
    {
        $all = explode("\n", str_replace("\r\n", "\n", $this->text));
        $start = array_search($heading, array_map(trim(...), $all), true);

        if ($start === false) {
            return null;
        }

        $lines = [];

        foreach (array_slice($all, $start + 1) as $line) {
            if (str_starts_with($line, '#')) {
                break;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param string $heading A heading line.
     *
     * @return list<list<string>>|null The rows of its first table after the separator, as trimmed cells; null when
     *                                 there is no such heading.
     */
    public function rows(string $heading): ?array
    {
        $lines = $this->lines($heading);

        if ($lines === null) {
            return null;
        }

        $table = [];

        foreach ($lines as $line) {
            if (!str_starts_with(trim($line), '|')) {
                continue;
            }

            $table[] = $this->cells(trim($line));
        }

        return array_slice($table, 2);
    }

    /**
     * @param string $heading A heading line.
     *
     * @return list<string> Its list items (`- ` or `1. `), each without its marker; empty when there is none.
     */
    public function items(string $heading): array
    {
        $items = [];

        foreach ($this->lines($heading) ?? [] as $line) {
            $matches = self::regexMatch('/^\\s*(?:[-*]|\\d+\\.)\\s+(.+)$/', $line);

            if (!array_key_exists(1, $matches)) {
                continue;
            }

            $items[] = trim($matches[1]);
        }

        return $items;
    }

    /**
     * @param string $row A table row, `| a | b |`.
     *
     * @return list<string> Its cells, trimmed; an escaped `\|` stays inside its cell.
     */
    private function cells(string $row): array
    {
        $cells = preg_split('/(?<!\\\\)\\|/', trim($row, '|'));
        $trimmed = [];

        foreach ($cells === false ? [] : $cells as $cell) {
            $trimmed[] = trim(str_replace('\\|', '|', $cell));
        }

        return $trimmed;
    }
}
