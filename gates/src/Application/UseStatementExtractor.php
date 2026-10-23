<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\RegexCaptures;

use function array_key_exists;
use function array_slice;
use function count;
use function explode;
use function is_array;
use function mb_ltrim;
use function mb_trim;
use function preg_match;
use function preg_replace;
use function token_get_all;

use const T_USE;

/**
 * Tokenizer-based extractor for top-level `use` statements.
 *
 * Distinguishes import-use (at namespace level) from trait-use (inside
 * a class body) using PHP's tokenizer + brace-depth tracking. Without
 * this distinction the require-checker would false-positive on every
 * `use SomeTrait;` line inside a class body.
 *
 * Handles single (`use Foo\Bar;`), aliased (`use Foo\Bar as Baz;`), and
 * grouped (`use Foo\{Bar, Baz as Qux};`) forms. `use function` and
 * `use const` statements are skipped (out of scope — they reference
 * php / ext-* symbols, not vendor packages).
 */
final readonly class UseStatementExtractor
{
    use RegexCaptures;

    /**
     * Extract every fully-qualified-name imported via a top-level `use`.
     *
     * @param string $source PHP source code.
     *
     * @return list<string>
     */
    public function extract(string $source): array
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $depth = 0;
        $fqns = [];

        for ($i = 0; $i < $count; $i += 1) {
            $depth += $this->depthDelta($tokens[$i]);
            [$newFqns, $i] = $this->processTokenAt($tokens, $i, $count, $depth);
            $fqns = [...$fqns, ...$newFqns];
        }

        return $fqns;
    }

    /**
     * Process one token. Returns the FQNs it contributed and the new cursor index.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens Full tokenizer output.
     * @param int                                           $start  Current cursor position.
     * @param int                                           $count  Total token count.
     * @param int                                           $depth  Current brace depth.
     *
     * @return array{0: list<string>, 1: int} Tuple `[contributed-fqns, new-cursor]`.
     */
    private function processTokenAt(array $tokens, int $start, int $count, int $depth): array
    {
        if (!$this->isTopLevelUseToken($tokens[$start], $depth)) {
            return [[], $start];
        }

        [$body, $endIndex] = $this->readUseBody($tokens, $start, $count);

        if ($body === '' || $this->isFunctionOrConstUse($body)) {
            return [[], $endIndex];
        }

        return [$this->parseUseBody($body), $endIndex];
    }

    /**
     * Track brace depth across the token stream — `{` opens, `}` closes.
     *
     * @param array{0: int, 1: string, 2: int}|string $token A single tokenizer token.
     */
    private function depthDelta(array|string $token): int
    {
        if ($token === '{') {
            return 1;
        }

        if ($token === '}') {
            return -1;
        }

        return 0;
    }

    /**
     * Decide whether `$token` is a `T_USE` keyword sitting at namespace level.
     *
     * @param array{0: int, 1: string, 2: int}|string $token The current tokenizer token.
     * @param int                                     $depth Current brace depth.
     */
    private function isTopLevelUseToken(array|string $token, int $depth): bool
    {
        return $depth === 0 && is_array($token) && $token[0] === T_USE;
    }

    /**
     * Slurp tokens forward from `$start` until `;` is consumed.
     *
     * When the stream ends without a `;` (e.g. a hand-rolled fixture
     * with `<?php use Foo\Bar` and no terminator), the body is
     * returned with the cursor parked one past the final token so
     * the outer loop's `$i += 1` lands at `$count` and exits.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens Full tokenizer output.
     * @param int                                           $start  Index of the `T_USE` token.
     * @param int                                           $count  Total token count.
     *
     * @return array{0: string, 1: int} Tuple `[body, cursor-after-body]`.
     */
    private function readUseBody(array $tokens, int $start, int $count): array
    {
        $body = '';

        // `foreach` over a preserve-keys slice: the iteration is bounded by
        // the slice length, with no counter to get wrong.
        $slice = array_slice($tokens, $start + 1, $count - $start - 1, true);

        foreach ($slice as $j => $next) {
            if ($next === ';') {
                return [mb_trim($body), $j];
            }

            $body .= is_array($next)
                ? $next[1]
                : $next;
        }

        return [mb_trim($body), $count];
    }

    /**
     * Decide whether `$body` is a `use function …` or `use const …` statement.
     *
     * @param string $body Trimmed body of a `use` statement.
     */
    private function isFunctionOrConstUse(string $body): bool
    {
        return preg_match('/^(function|const)\\s+/i', $body) === 1;
    }

    /**
     * Parse the body of a top-level `use` into the list of FQNs it imports.
     *
     * @param string $body Trimmed body of a `use` statement.
     *
     * @return list<string>
     */
    private function parseUseBody(string $body): array
    {
        $groupMatch = self::regexMatch('/^([A-Za-z0-9_\\\\]+)\\\\\\{([^}]+)\\}$/', $body);

        if (array_key_exists(1, $groupMatch) && array_key_exists(2, $groupMatch)) {
            return $this->parseGrouped($groupMatch[1], $groupMatch[2]);
        }

        $clean = $this->stripUseAlias($body);

        return $clean === ''
            ? []
            : [$clean];
    }

    /**
     * Expand a grouped use (`use Prefix\{A, B as C};`) into a list of FQNs.
     *
     * @param string $prefix  Common namespace prefix.
     * @param string $members Comma-separated member list.
     *
     * @return list<string>
     */
    private function parseGrouped(string $prefix, string $members): array
    {
        $cleanPrefix = mb_ltrim($prefix, '\\');
        $fqns = [];

        foreach (explode(',', $members) as $member) {
            $clean = $this->stripUseAlias($member);

            if ($clean === '') {
                continue;
            }

            $fqns[] = $cleanPrefix . '\\' . mb_ltrim($clean, '\\');
        }

        return $fqns;
    }

    /**
     * Strip a trailing `as Alias` clause and normalise leading backslashes.
     *
     * @param string $member One member of a use-statement body.
     */
    private function stripUseAlias(string $member): string
    {
        $stripped = (string) preg_replace('/\\s+as\\s+.*$/i', '', mb_trim($member));

        return mb_ltrim($stripped, '\\');
    }
}
