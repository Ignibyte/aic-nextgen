<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use function implode;
use function preg_match;
use function str_contains;
use function str_replace;
use function substr;

/**
 * A table as CSV text (#209), for a job or a Stream to write to a file and
 * send as a `FileDownload`: RFC 4180, each line ended by CRLF, a field quoted
 * when it holds a comma, a quote, CR or LF, and its quotes doubled. A field a
 * spreadsheet would run as a formula, one starting with `=`, `+`, `-`, `@`, a
 * tab or CR, gets a leading `'`, unless it is a plain number such as `-12`
 * or `3.50`, which stays a number.
 */
final readonly class CsvDocument
{
    /**
     * The first characters a spreadsheet reads as the start of a formula.
     */
    private const string FORMULA_STARTS = "=+-@\t\r";

    /**
     * @param list<string>       $header The column names, the first line.
     * @param list<list<string>> $rows   The rows, each a list of fields in the header's order.
     */
    public function __construct(
        private array $header,
        private array $rows,
    ) {
    }

    /**
     * @return string The header and the rows, each line ended by CRLF.
     */
    public function text(): string
    {
        $text = self::line($this->header);

        foreach ($this->rows as $row) {
            $text .= self::line($row);
        }

        return $text;
    }

    /**
     * @param list<string> $fields One line's fields.
     *
     * @return string The line, ended by CRLF.
     */
    private static function line(array $fields): string
    {
        $written = [];

        foreach ($fields as $field) {
            $written[] = self::field(self::guarded($field));
        }

        return implode(',', $written) . "\r\n";
    }

    /**
     * @param string $value A field, guarded.
     *
     * @return string The field, quoted with its quotes doubled when it holds a comma, a quote, CR or LF.
     */
    private static function field(string $value): string
    {
        return preg_match('/[",\\r\\n]/', $value) === 1
            ? '"' . str_replace('"', '""', $value) . '"'
            : $value;
    }

    /**
     * @param string $value A field.
     *
     * @return string The field after a `'` when a spreadsheet would read it as a formula; a plain number as it is.
     */
    private static function guarded(string $value): string
    {
        if ($value === '' || !str_contains(self::FORMULA_STARTS, substr($value, 0, 1))) {
            return $value;
        }

        return preg_match('/^-?\\d+(\\.\\d+)?$/', $value) === 1
            ? $value
            : "'" . $value;
    }
}
