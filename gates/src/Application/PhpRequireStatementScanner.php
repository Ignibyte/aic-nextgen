<?php

declare(strict_types=1);

namespace Gates\Application;

use function count;
use function in_array;
use function is_array;
use function token_get_all;

use const T_COMMENT;
use const T_CONST;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DIR;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_FUNCTION;
use const T_INCLUDE;
use const T_INCLUDE_ONCE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NS_SEPARATOR;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_REQUIRE;
use const T_REQUIRE_ONCE;
use const T_STRING;
use const T_WHITESPACE;

/**
 * Classifies every `require` / `include` construct in a PHP source string as
 * either compile-time-constant ("static") or runtime-dynamic.
 *
 * Token-level by necessity, not by preference. A textual `\brequire\b` scan is
 * unusable in this repo: `AIC\Config\Application\Config` declares a private
 * method literally NAMED `require`, and `requireInt()` /
 * `requireNonEmptyString()` / `requireType()` / a `$require` variable / the
 * word "include" inside an exception message all appear in real source.
 * `token_get_all()` separates the language construct from those identifiers —
 * with three caveats this scanner handles explicitly, each verified against the
 * PHP 8.5 lexer rather than assumed:
 *
 *   - `private function require(...)` DOES emit `T_REQUIRE` (preceded by
 *     `T_FUNCTION`) — PHP permits reserved words as method names.
 *   - `self::require(...)` DOES emit `T_REQUIRE` (preceded by `T_DOUBLE_COLON`).
 *   - `$this->require(...)` does NOT — after `->` the lexer emits `T_STRING`.
 *
 * The "static" operand grammar deliberately mirrors what the elephc AOT
 * compiler's `fold_include_path` actually folds after #582 H1 — string
 * literals, `__DIR__`, global constants, and `.` concatenation of those.
 * `__FILE__` is NOT folded (H1 threaded only the directory), and a function
 * call such as `dirname(__DIR__)` is not foldable either, so both classify as
 * dynamic here. Consumed by {@see NoDynamicRequireGate} (gate:16).
 */
final readonly class PhpRequireStatementScanner
{
    /**
     * The four include-family language constructs.
     */
    private const array INCLUDE_TOKENS = [
        T_REQUIRE,
        T_REQUIRE_ONCE,
        T_INCLUDE,
        T_INCLUDE_ONCE,
    ];

    /**
     * Tokens carrying no syntactic weight, skipped when looking backward or
     * forward for the next significant token.
     */
    private const array INSIGNIFICANT_TOKENS = [
        T_WHITESPACE,
        T_COMMENT,
        T_DOC_COMMENT,
    ];

    /**
     * When one of these immediately precedes an include token, the word is
     * being used as an IDENTIFIER (a method declaration or a static call), not
     * as the language construct.
     */
    private const array IDENTIFIER_CONTEXT_TOKENS = [
        T_FUNCTION,
        T_DOUBLE_COLON,
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_CONST,
    ];

    /**
     * Operand tokens that keep an include path compile-time-constant. PHP 8
     * lexes qualified constant names as single `T_NAME_QUALIFIED` /
     * `T_NAME_FULLY_QUALIFIED` tokens (`\FQ\CONST` never emits
     * `T_NS_SEPARATOR` + `T_STRING` on 8.x; the separator stays listed for
     * grouped-use edge spellings only).
     */
    private const array STATIC_OPERAND_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DIR,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_QUALIFIED,
        T_NS_SEPARATOR,
        T_STRING,
    ];

    /**
     * Single-character tokens that end the operand expression. `:` matters
     * because the sanctioned seam spelling puts the construct inside a
     * ternary — `is_file(__DIR__ . '/x.php') ? require __DIR__ . '/x.php' : []`
     * — and the else-branch must not be mistaken for part of the path. `?` is
     * deliberately NOT a terminator: a `?` after the operand means
     * the require's operand is ITSELF a runtime conditional
     * (`require FOO ? 'a.php' : 'b.php'`), which elephc cannot fold — the
     * walk must classify it dynamic, not end with a static verdict.
     */
    private const array OPERAND_TERMINATORS = [';', ',', ':', ')', ']'];

    /**
     * Find every runtime-dynamic include construct in `$source`.
     *
     * @param string $source Raw PHP source text.
     *
     * @return list<array{line: int, construct: string}> One entry per dynamic site, in source order.
     */
    public function scan(string $source): array
    {
        $tokens = token_get_all($source);
        $violations = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if (!$this->isDynamicIncludeSite($tokens, $index, $token)) {
                continue;
            }

            $violations[] = ['line' => $token[2], 'construct' => $token[1]];
        }

        return $violations;
    }

    /**
     * Decide whether the (array) token at `$index` is an include-family
     * CONSTRUCT (not an identifier usage) whose operand is runtime-dynamic.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Full token stream.
     * @param int                                                 $index  Index of the candidate token.
     * @param array{0: int, 1: string, 2: int}                    $token  The candidate token itself.
     */
    private function isDynamicIncludeSite(array $tokens, int $index, array $token): bool
    {
        if (!in_array($token[0], self::INCLUDE_TOKENS, true)) {
            return false;
        }

        if ($this->isIdentifierUsage($tokens, $index)) {
            return false;
        }

        return !$this->operandIsStatic($tokens, $index);
    }

    /**
     * Decide whether the include token at `$index` is really an identifier —
     * a method named `require`, or a `self::require()` call.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Full token stream.
     * @param int                                                 $index  Index of the include token.
     */
    private function isIdentifierUsage(array $tokens, int $index): bool
    {
        // Token 0 is always significant (token_get_all never emits an
        // include construct without a leading T_OPEN_TAG), so the backward
        // walk always meets a significant token and returns from inside
        // the loop — no unreachable post-loop fallthrough.
        for ($cursor = $index - 1;; $cursor -= 1) {
            $token = $tokens[$cursor];

            if (!$this->isInsignificant($token)) {
                return is_array($token) && in_array($token[0], self::IDENTIFIER_CONTEXT_TOKENS, true);
            }
        }
    }

    /**
     * Walk the operand expression following the include token and decide
     * whether every part of it is compile-time-constant.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Full token stream.
     * @param int                                                 $index  Index of the include token.
     */
    private function operandIsStatic(array $tokens, int $index): bool
    {
        $count = count($tokens);

        for ($cursor = $index + 1; $cursor < $count; $cursor += 1) {
            $verdict = $this->operandTokenVerdict($tokens[$cursor]);

            if ($verdict !== null) {
                return $verdict;
            }
        }

        return false;
    }

    /**
     * Classify one operand token: `null` keeps walking (whitespace/comment,
     * static-operand token, `.` concatenation); `true` ends the operand at a
     * terminator with everything seen static; `false` marks the operand
     * dynamic (variable, function call, interpolation, …).
     *
     * @param array{0: int, 1: string, 2: int}|string $token Token from the stream.
     */
    private function operandTokenVerdict(array|string $token): ?bool
    {
        if ($this->isInsignificant($token)) {
            return null;
        }

        if (is_array($token)) {
            return in_array($token[0], self::STATIC_OPERAND_TOKENS, true)
                ? null
                : false;
        }

        if ($token === '.') {
            return null;
        }

        return in_array($token, self::OPERAND_TERMINATORS, true);
    }

    /**
     * Decide whether a token carries no syntactic weight.
     *
     * @param array{0: int, 1: string, 2: int}|string $token Token from the stream.
     */
    private function isInsignificant(array|string $token): bool
    {
        return is_array($token) && in_array($token[0], self::INSIGNIFICANT_TOKENS, true);
    }
}
