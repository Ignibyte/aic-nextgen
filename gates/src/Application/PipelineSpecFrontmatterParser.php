<?php

declare(strict_types=1);

namespace Gates\Application;

use function count;
use function explode;
use function mb_ltrim;
use function mb_substr;
use function mb_trim;
use function preg_match;
use function str_starts_with;

/**
 * Parses YAML frontmatter from a markdown file.
 *
 * Recognizes a leading `---\n…\n---\n` block at file start; supports
 * top-level `key: value` pairs only. Multi-line values (lists, nested maps)
 * collapse to a single-line representation: the key maps to its raw
 * multi-line text. Callers use `array_key_exists` checks for required-field
 * detection only; deep validation of list items is deferred to dedicated
 * checks in `PipelineSpecSyncGate`.
 *
 * Extracted from `PipelineSpecSyncGate` per ticket #56 to keep the gate
 * class under PHPMD class-length / class-complexity thresholds.
 *
 * @api
 */
final readonly class PipelineSpecFrontmatterParser
{
    /**
     * Split a markdown file into its YAML frontmatter and body.
     *
     * Returns `null` when no leading `---\n` is present.
     *
     * @param string $contents Full markdown file contents.
     *
     * @return array{frontmatter: array<string, string>, body: string}|null
     */
    public function split(string $contents): ?array
    {
        if (!str_starts_with($contents, "---\n")) {
            return null;
        }

        // Strip the leading delimiter (4 ASCII bytes — safe to slice by char count
        // because `---\n` is single-byte) and split on the first closing
        // `\n---\n`. `explode` is byte-safe and sidesteps the
        // `preg_match` + `mb_substr` mismatch that mis-slices when the YAML
        // contains multi-byte characters (em-dashes, etc.).
        $rest = mb_substr($contents, 4);
        $parts = explode("\n---\n", $rest, 2);

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'body' => $parts[1],
            'frontmatter' => $this->parseFields($parts[0]),
        ];
    }

    /**
     * Parse a small subset of YAML — top-level `key: value` pairs only.
     *
     * @param string $yaml Raw YAML body (frontmatter contents between the `---` delimiters).
     *
     * @return array<string, string>
     */
    private function parseFields(string $yaml): array
    {
        // `preg_split` with a literal pattern can never fail at runtime
        // (the `false` return is reserved for invalid regex). `explode`
        // is byte-safe and avoids the dead `=== false` branch.
        $state = ['fields' => [], 'currentKey' => null];

        foreach (explode("\n", $yaml) as $line) {
            $state = $this->absorbLine($state, $line);
        }

        return $state['fields'];
    }

    /**
     * Absorb a single YAML line into the running field map.
     *
     * Returns the new parser state (running map + current key).
     *
     * @param array{fields: array<string, string>, currentKey: string|null} $state Current parser state.
     * @param string                                                        $line  The raw YAML line.
     *
     * @return array{fields: array<string, string>, currentKey: string|null}
     */
    private function absorbLine(array $state, string $line): array
    {
        if ($this->isSkippableLine($line)) {
            return $state;
        }

        if (preg_match('/^(\\w+)\\s*:\\s*(.*)$/', $line, $matches) === 1) {
            $key = $matches[1];
            $fields = $state['fields'];
            $fields[$key] = mb_trim($matches[2]);

            return ['fields' => $fields, 'currentKey' => $key];
        }

        if ($state['currentKey'] === null) {
            return $state;
        }

        $fields = $state['fields'];
        $fields[$state['currentKey']] = mb_trim($fields[$state['currentKey']] . "\n" . $line);

        return ['fields' => $fields, 'currentKey' => $state['currentKey']];
    }

    /**
     * Blank lines + comment-only lines are skipped during field parsing.
     *
     * @param string $line The raw YAML line.
     */
    private function isSkippableLine(string $line): bool
    {
        return $line === '' || str_starts_with(mb_ltrim($line), '#');
    }
}
