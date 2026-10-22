<?php

declare(strict_types=1);

namespace Gates\Domain;

use function explode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * One markdown page as gate:29 reads it: its headings, links, code, prose and
 * table cells, each with its line number. Code is every inline code span and
 * every line inside a fenced block; prose is every other line with its code
 * spans taken out, so quoted code is never judged as writing.
 */
final readonly class MarkdownDocument
{
    /**
     * The page's lines, numbered from 1.
     *
     * @var array<int, string>
     */
    private array $lines;

    /**
     * @param string $page     The page's file name (`kinds.md`).
     * @param string $contents The page's text.
     */
    public function __construct(public string $page, private string $contents)
    {
        $lines = [];

        foreach (explode("\n", $contents) as $index => $line) {
            $lines[$index + 1] = $line;
        }

        $this->lines = $lines;
    }

    /**
     * @param string $needle Text to look for.
     *
     * @return bool Whether the page holds it anywhere, code included.
     */
    public function mentions(string $needle): bool
    {
        return str_contains($this->contents, $needle);
    }

    /**
     * @return array<int, string> Every line, numbered from 1.
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return array<int, string> Every line outside a fenced block, its code spans taken out.
     */
    public function prose(): array
    {
        $prose = [];

        foreach ($this->outsideFences() as $number => $line) {
            $prose[$number] = (string) preg_replace('/`[^`]+`/', '', $line);
        }

        return $prose;
    }

    /**
     * @return list<array{int, string}> Every code span and fenced line, with its line number.
     */
    public function code(): array
    {
        $code = [];

        foreach ($this->insideFences() as $number => $line) {
            $code[] = [$number, $line];
        }

        foreach ($this->outsideFences() as $number => $line) {
            preg_match_all('/`([^`]+)`/', $line, $matches);

            foreach ($matches[1] as $span) {
                $code[] = [$number, $span];
            }
        }

        return $code;
    }

    /**
     * @return list<array{int, int, string}> Every heading: its line, its level and its text.
     */
    public function headings(): array
    {
        $headings = [];

        foreach ($this->outsideFences() as $number => $line) {
            if (preg_match('/^(#{1,6})\\s+(.+?)\\s*$/', $line, $match) !== 1) {
                continue;
            }

            $headings[] = [$number, strlen($match[1]), $match[2]];
        }

        return $headings;
    }

    /**
     * @return list<array{int, string}> Every link target in prose, with its line number.
     */
    public function links(): array
    {
        $links = [];

        foreach ($this->prose() as $number => $line) {
            preg_match_all('/\\]\\(([^)\\s]+)\\)/', $line, $matches);

            foreach ($matches[1] as $target) {
                $links[] = [$number, $target];
            }
        }

        return $links;
    }

    /**
     * @return list<array{int, string}> The first cell of every table row but separators, with its line number.
     */
    public function firstCells(): array
    {
        $cells = [];

        foreach ($this->outsideFences() as $number => $line) {
            if (
                preg_match('/^\\|\\s*([^|]*?)\\s*\\|/', $line, $match) !== 1
                || preg_match('/^[-:\\s]*$/', $match[1]) === 1
            ) {
                continue;
            }

            $cells[] = [$number, $match[1]];
        }

        return $cells;
    }

    /**
     * @return array<int, string> The lines outside fenced blocks, fence lines excluded.
     */
    private function outsideFences(): array
    {
        return $this->fenced(false);
    }

    /**
     * @return array<int, string> The lines inside fenced blocks, fence lines excluded.
     */
    private function insideFences(): array
    {
        return $this->fenced(true);
    }

    /**
     * @param bool $inside Whether to keep the lines inside fences or the ones outside.
     *
     * @return array<int, string> The kept lines, by number.
     */
    private function fenced(bool $inside): array
    {
        $kept = [];
        $fence = false;

        foreach ($this->lines as $number => $line) {
            if (str_starts_with(trim($line), '```')) {
                $fence = !$fence;

                continue;
            }

            if ($fence !== $inside) {
                continue;
            }

            $kept[$number] = $line;
        }

        return $kept;
    }
}
