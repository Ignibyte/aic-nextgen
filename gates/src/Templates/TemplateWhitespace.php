<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateViolation;

use function array_filter;
use function array_keys;
use function array_pop;
use function count;
use function explode;
use function mb_trim;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strspn;

/**
 * gate:25's line rules (#170): spaces, never tabs; LF line ends; nothing
 * after the last character of a line; indentation in steps of 4; no two
 * blank lines together and no blank line at the end. A template may end with
 * or without its newline: an atom without one keeps its output free of a
 * trailing newline.
 */
final readonly class TemplateWhitespace
{
    /** The indentation step. */
    private const int STEP = 4;

    /**
     * @param string $path   The template, relative to the root.
     * @param string $source Its source.
     *
     * @return list<TemplateViolation> One `whitespace` violation per broken line rule, by line.
     */
    public function violations(string $path, string $source): array
    {
        $lines = $this->lines($source);
        $violations = [];
        $blank = false;

        foreach ($lines as $index => $line) {
            foreach ($this->details($line, $blank) as $detail) {
                $violations[] = new TemplateViolation($path, $index + 1, TemplateRefusal::Whitespace, $detail);
            }

            $blank = mb_trim($line) === '';
        }

        return [...$violations, ...$this->end($path, $lines)];
    }

    /**
     * @param string $source A template.
     *
     * @return list<string> Its lines, without their newlines; a final newline ends the last line, not a new one.
     */
    private function lines(string $source): array
    {
        $lines = explode("\n", $source);

        if (str_ends_with($source, "\n")) {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param string       $path  The template.
     * @param list<string> $lines Its lines.
     *
     * @return list<TemplateViolation> A blank last line.
     */
    private function end(string $path, array $lines): array
    {
        $last = count($lines) - 1;
        $detail = 'a blank line at the end: end the file after its last line';

        return $last > 0 && mb_trim($lines[$last] ?? '') === ''
            ? [new TemplateViolation($path, $last + 1, TemplateRefusal::Whitespace, $detail)]
            : [];
    }

    /**
     * @param string $line           One line, without its newline.
     * @param bool   $afterBlankLine Whether the line before it is blank.
     *
     * @return list<string> What the line breaks.
     */
    private function details(string $line, bool $afterBlankLine): array
    {
        $indent = strspn($line, ' ');
        $checks = [
            'a carriage return: save the file with LF line ends' => str_contains($line, "\r"),
            'a tab: indent with spaces' => str_contains($line, "\t"),
            'trailing whitespace: end the line at its last character' => rtrim($line, " \t\r") !== rtrim($line, "\r"),
            'two blank lines together: keep one' => $afterBlankLine && mb_trim($line) === '',
            sprintf('an indentation of %d spaces: indent in steps of %d', $indent, self::STEP) => $indent % self::STEP !== 0
                && $indent < strlen($line),
        ];

        return array_keys(array_filter($checks));
    }
}
