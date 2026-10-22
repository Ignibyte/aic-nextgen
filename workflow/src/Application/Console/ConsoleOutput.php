<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use function array_map;
use function count;
use function implode;
use function json_encode;
use function max;
use function rtrim;
use function str_pad;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * How the inspect commands answer (#109): plain text, one item per line in
 * columns padded with two spaces under a header line; or, with `--json`, one
 * `aic.console/1` envelope on one line, keys in order.
 */
final readonly class ConsoleOutput
{
    /**
     * The envelope's schema id.
     */
    public const string SCHEMA = 'aic.console/1';

    /**
     * @param list<string>       $header The column names.
     * @param list<list<string>> $rows   One row per item, a cell per column.
     * @param string             $empty  The line when there are no rows.
     *
     * @return list<string> The header and the rows, padded into columns; the empty line when there are no rows.
     */
    public function table(array $header, array $rows, string $empty): array
    {
        if ($rows === []) {
            return [$empty];
        }

        $widths = array_map(strlen(...), $header);

        foreach ($rows as $row) {
            foreach ($row as $column => $cell) {
                $widths[$column] = max($widths[$column] ?? 0, strlen($cell));
            }
        }

        return array_map(static fn (array $row): string => self::line($row, $widths), [$header, ...$rows]);
    }

    /**
     * @param string       $command The command.
     * @param string       $data    Its data, one JSON value.
     * @param list<string> $notes   What the answer noted, such as a stale surface; none when empty.
     *
     * @return string The envelope: `command`, `data`, `notes` when there are any, `schema`.
     */
    public function envelope(string $command, string $data, array $notes): string
    {
        $noted = $notes === []
            ? ''
            : ',"notes":' . self::json($notes);

        return '{"command":' . self::json($command) . ',"data":' . $data . $noted . ',"schema":"' . self::SCHEMA . '"}';
    }

    /**
     * @param string $command The command.
     * @param string $error   Why it did not answer.
     * @param int    $exit    Its exit code.
     *
     * @return string The error envelope.
     */
    public function error(string $command, string $error, int $exit): string
    {
        return self::json(['command' => $command, 'error' => $error, 'exit' => $exit, 'schema' => self::SCHEMA]);
    }

    /**
     * @param list<string>    $row    A row's cells.
     * @param array<int, int> $widths Each column's width.
     *
     * @return string The row, each cell but the last padded to its column.
     */
    private static function line(array $row, array $widths): string
    {
        $cells = [];
        $last = count($row) - 1;

        foreach ($row as $column => $cell) {
            $cells[] = $column === $last
                ? $cell
                : str_pad($cell, $widths[$column] ?? 0);
        }

        return rtrim(implode('  ', $cells));
    }

    /**
     * @param array<string, int|string>|list<string>|string $value A value.
     *
     * @return string It as JSON.
     */
    private static function json(array|string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
