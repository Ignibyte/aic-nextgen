<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Wiki;

use function array_fill;
use function array_map;
use function array_slice;
use function count;
use function implode;
use function sprintf;
use function str_replace;

/**
 * The wiki's Markdown (#108, after Droost's page renderer): tables capped at
 * forty rows with the rest counted, cells with `|`, `[` and `]` escaped and
 * newlines flattened so no text from the code can break a row or form a link,
 * code spans, and bullet lists that say "none" when empty.
 */
final readonly class WikiMarkdown
{
    /**
     * The rows a table shows before it counts the rest.
     */
    private const int MAX_ROWS = 40;

    /**
     * @param list<string>       $header The column names.
     * @param list<list<string>> $rows   One row per item; cells already escaped or built by this class.
     * @param string             $more   Where the rows past the cap are listed, such as `bin/aic code:tree note`.
     *
     * @return list<string> The table's lines, or `none` when there are no rows.
     */
    public function table(array $header, array $rows, string $more): array
    {
        if ($rows === []) {
            return ['none'];
        }

        $lines = [self::row($header), self::row(array_fill(0, count($header), '---'))];

        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $lines[] = self::row($row);
        }

        $rest = count($rows) - self::MAX_ROWS;

        return $rest > 0
            ? [...$lines, '', sprintf('and %d more: `%s`', $rest, $more)]
            : $lines;
    }

    /**
     * @param string       $heading A heading line, such as `## Routes`.
     * @param list<string> $body    Its lines.
     *
     * @return list<string> The heading, a blank line, the body and a blank line.
     */
    public function section(string $heading, array $body): array
    {
        return [$heading, '', ...$body, ''];
    }

    /**
     * @param list<string> $items The items, already escaped or built by this class.
     *
     * @return list<string> One bullet per item, or `none`.
     */
    public function bullets(array $items): array
    {
        return $items === [] ? ['none'] : array_map(static fn (string $item): string => '- ' . $item, $items);
    }

    /**
     * @param list<string> $items Items, already escaped or built by this class.
     *
     * @return string Them joined by commas, or `none`.
     */
    public function inline(array $items): string
    {
        return $items === []
            ? 'none'
            : implode(', ', $items);
    }

    /**
     * @param string $text Text from the code: a name, a path, a cadence.
     *
     * @return string It in a code span, escaped for a table cell.
     */
    public function code(string $text): string
    {
        return '`' . $this->cell($text) . '`';
    }

    /**
     * @param array<string, string> $schedule A surface schedule: `name`, `cadence`, `job`.
     *
     * @return list<string> Its name, cadence and job as code spans.
     */
    public function schedule(array $schedule): array
    {
        return [$this->code($schedule['name'] ?? ''), $this->code($schedule['cadence'] ?? ''), $this->code($schedule['job'] ?? '')];
    }

    /**
     * @param string $text Link text.
     * @param string $href A relative target.
     *
     * @return string The link.
     */
    public function link(string $text, string $href): string
    {
        return '[' . $this->cell($text) . '](' . $href . ')';
    }

    /**
     * @param string $text Text from the code.
     *
     * @return string It with `|`, `[` and `]` escaped and newlines flattened.
     */
    public function cell(string $text): string
    {
        return str_replace(["\r\n", "\n", '|', '[', ']'], [' ', ' ', '\\|', '\\[', '\\]'], $text);
    }

    /**
     * @param list<string> $cells A row's cells.
     *
     * @return string The row.
     */
    private static function row(array $cells): string
    {
        return '| ' . implode(' | ', $cells) . ' |';
    }
}
