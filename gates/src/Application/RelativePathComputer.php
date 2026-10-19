<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;

use function array_key_exists;
use function array_slice;
use function count;
use function explode;
use function implode;
use function rtrim;
use function sprintf;
use function str_repeat;
use function str_starts_with;

/**
 * Pure-function relativizer: compute the shortest path-segment-relative
 * path connecting two absolute filesystem paths.
 *
 * Used by `WorktreeGitdirRelativizer` to convert a worktree's host-absolute
 * `gitdir:` line into the host/container-equivalent relative form. The
 * algorithm splits both inputs into `/`-separated segments, walks the
 * common prefix, emits `..` once per remaining `$fromDir` segment, then
 * appends the tail of `$toAbsolute`. Joined with `/`. Identical inputs
 * return `.`.
 *
 * No I/O. No global state. No autoload-time cost beyond the class file.
 * Stand-alone PHPUnit-testable with captured string fixtures.
 *
 * Hand-rolled rather than pulled in via `symfony/filesystem` — the helper
 * is ~30 lines of pure segment math and Constitution §0 (no carve-outs,
 * source-fix-only, surface-minimum) does not earn a vendor dependency for
 * one call site.
 *
 * Per pipeline 18116130-1939-4c9e-aa21-842680252f4a (ticket #172).
 *
 * @api Public — used by `WorktreeGitdirRelativizer` and isolated for
 *      mutation-grade unit testing.
 */
final readonly class RelativePathComputer
{
    /**
     * Compute the relative path from `$fromDir` to `$toAbsolute`.
     *
     * Both inputs MUST be absolute (leading `/`) and normalized — no `.`
     * segments, no `..` segments, no double slashes. Trailing slashes are
     * tolerated and stripped. The caller (`WorktreeGitdirRelativizer`)
     * pre-normalizes via `realpath` before invoking this method; tests
     * exercise the normalized-input contract directly.
     *
     * Result is the relative path you would `cd`-into from `$fromDir` to
     * land at `$toAbsolute`. Identical paths return `.`.
     *
     * @param string $fromDir    Absolute, normalized source directory.
     * @param string $toAbsolute Absolute, normalized target path.
     *
     * @throws InvalidArgumentException When either input is not absolute.
     */
    public function compute(string $fromDir, string $toAbsolute): string
    {
        $this->assertAbsolute($fromDir, 'fromDir');
        $this->assertAbsolute($toAbsolute, 'toAbsolute');

        $fromSegments = $this->segments($fromDir);
        $toSegments = $this->segments($toAbsolute);

        $commonLength = $this->commonPrefixLength($fromSegments, $toSegments);

        $upMoves = count($fromSegments) - $commonLength;
        $downSegments = array_slice($toSegments, $commonLength);

        if ($upMoves === 0 && $downSegments === []) {
            return '.';
        }

        // One concatenation covers every case: `str_repeat('../', 0)` returns
        // '' and the outer rtrim absorbs any trailing
        // slash from the up-segments OR a no-down case.
        return rtrim(
            str_repeat('../', $upMoves) . implode('/', $downSegments),
            '/',
        );
    }

    /**
     * Reject non-absolute inputs with a `$label`-named message — keeps the
     * computer pure-function-clean by refusing to silently coerce relative
     * input to absolute.
     *
     * @param string $path  The input to validate.
     * @param string $label Argument name used in the failure message.
     *
     * @throws InvalidArgumentException When `$path` does not begin with `/`.
     */
    private function assertAbsolute(string $path, string $label): void
    {
        if (!str_starts_with($path, '/')) {
            throw new InvalidArgumentException(sprintf(
                'RelativePathComputer: %s must be an absolute path, got %s.',
                $label,
                $path === '' ? '(empty string)' : $path,
            ));
        }
    }

    /**
     * Split a normalized absolute path into its `/`-delimited segments.
     *
     * The leading empty segment that the `/` prefix produces is RETAINED,
     * not stripped: every absolute path carries it, so it is always counted
     * into `commonPrefixLength` and cancels exactly out of both `upMoves`
     * (`count − common`) and `downSegments` (`array_slice(to, common)`) —
     * `compute()` output is identical whether or not it is present. A stray
     * trailing slash is absorbed by `rtrim` before the split.
     *
     * @param string $path Absolute, normalized path.
     *
     * @return list<string>
     */
    private function segments(string $path): array
    {
        // Retaining the leading '' keeps this method free of the
        // `array_slice(explode(...), 1)` wrapper whose offset literal and
        // slice call were a DecrementInteger / UnwrapArraySlice mutator
        // surface — both equivalent under the commonPrefixLength
        // cancellation, both removed structurally per ticket #186.
        return explode('/', rtrim($path, '/'));
    }

    /**
     * Count the number of leading segments shared by both arrays.
     *
     * Both inputs come from `segments()`, so for any absolute path each list
     * carries a leading `''` at index 0 — the first element is therefore a
     * guaranteed shared match. That `''` is counted into the result and
     * cancels out of `compute()`'s `upMoves` / `downSegments` arithmetic.
     *
     * @param list<string> $firstSegments  First segment list (typically `$fromDir`'s).
     * @param list<string> $secondSegments Second segment list (typically `$toAbsolute`'s).
     */
    private function commonPrefixLength(array $firstSegments, array $secondSegments): int
    {
        // Return-on-mismatch instead of a `$shared` accumulator: at the first
        // index whose key is absent from `$secondSegments`, or whose value
        // differs, the shared-prefix length IS that index. A `$shared = 0`
        // initializer would be dead code — `segments()` never returns an empty
        // list, so this loop always runs at least once and the initializer's
        // value is never observable; its `0` literal would be a DecrementInteger
        // mutator surface that no test could kill. Returning the index (or the
        // full count when every segment matches) removes that surface entirely.
        foreach ($firstSegments as $index => $segment) {
            if (!array_key_exists($index, $secondSegments)) {
                return $index;
            }

            if ($secondSegments[$index] !== $segment) {
                return $index;
            }
        }

        return count($firstSegments);
    }
}
