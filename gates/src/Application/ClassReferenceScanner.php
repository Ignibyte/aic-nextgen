<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\SourceSymbols;

use function array_keys;
use function in_array;
use function is_array;
use function ltrim;
use function str_contains;
use function token_get_all;

use const T_CASE;
use const T_CLASS;
use const T_COMMENT;
use const T_CONST;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_ENUM;
use const T_FUNCTION;
use const T_INTERFACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_STRING;
use const T_TRAIT;
use const T_WHITESPACE;

/**
 * Reads the class references out of a PHP file with the tokenizer (gate:21).
 *
 * Ported from the elephc bundler's greedy scan: every name token outside a
 * member position becomes a candidate, both as written and qualified by the
 * file's namespace, and resolving the candidates against the PSR-4 map keeps
 * the real ones. In one-class-per-file PSR-4 code that catches `use` lines,
 * qualified names, bare same-namespace names, trait uses and attributes
 * without a full name resolver.
 */
final readonly class ClassReferenceScanner
{
    /**
     * Token ids that can name a class.
     */
    private const array NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    /**
     * Previous tokens after which a name is a member, method, constant or case, not a class.
     */
    private const array MEMBER_POSITIONS = [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_FUNCTION, T_CONST, T_CASE];

    /**
     * Token ids that open a class-like declaration.
     */
    private const array DECLARATION_TOKENS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    /**
     * Scan one source file.
     *
     * @param string $source PHP source text.
     *
     * @return SourceSymbols What the file declares and the class names it may refer to.
     */
    public function scan(string $source): SourceSymbols
    {
        $tokens = $this->significantTokens($source);
        $namespace = '';
        $declared = null;
        $candidates = [];

        foreach ($tokens as $index => [$id, $text]) {
            $previous = $this->previousToken($tokens, $index);
            $next = $tokens[$index + 1] ?? null;

            $declaredNamespace = $this->namespaceName($id, $next);

            if ($declaredNamespace !== null) {
                $namespace = $declaredNamespace;

                continue;
            }

            $declared ??= $this->declaredName($id, $previous, $next, $namespace);

            foreach ($this->referencedNames($id, $text, $previous, $namespace) as $name) {
                $candidates[$name] = true;
            }
        }

        return new SourceSymbols($declared, array_keys($candidates));
    }

    /**
     * @param list<array{0: int|string, 1: string}> $tokens Significant tokens.
     * @param int                                   $index  The current token's position.
     *
     * @return int|string|null The previous token's id, absent at the start.
     */
    private function previousToken(array $tokens, int $index): int|string|null
    {
        return $index === 0 ? null : ($tokens[$index - 1][0] ?? null);
    }

    /**
     * Tokenize and drop whitespace and comments; char tokens become `[char, char]`.
     *
     * @param string $source PHP source text.
     *
     * @return list<array{0: int|string, 1: string}>
     */
    private function significantTokens(string $source): array
    {
        $significant = [];

        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                $significant[] = [$token, $token];

                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $significant[] = [$token[0], $token[1]];
        }

        return $significant;
    }

    /**
     * The FQCN a class-like declaration names, or null when this token opens none.
     *
     * `Foo::class` and `new class` are not declarations of a named class.
     *
     * @param int|string                           $id        Current token id.
     * @param int|string|null                      $previous  Previous token id.
     * @param array{0: int|string, 1: string}|null $next      Next token.
     * @param string                               $namespace The file's namespace.
     *
     * @return string|null The declared FQCN.
     */
    private function declaredName(int|string $id, int|string|null $previous, ?array $next, string $namespace): ?string
    {
        if (!in_array($id, self::DECLARATION_TOKENS, true) || in_array($previous, [T_DOUBLE_COLON, T_NEW], true)) {
            return null;
        }

        if ($next === null || $next[0] !== T_STRING) {
            return null;
        }

        return $namespace === ''
            ? $next[1]
            : $namespace . '\\' . $next[1];
    }

    /**
     * The namespace a `namespace` statement declares, or null when this token opens none.
     *
     * @param int|string                           $id   Current token id.
     * @param array{0: int|string, 1: string}|null $next Next token.
     *
     * @return string|null The declared namespace.
     */
    private function namespaceName(int|string $id, ?array $next): ?string
    {
        if ($id !== T_NAMESPACE || $next === null || !in_array($next[0], self::NAME_TOKENS, true)) {
            return null;
        }

        return $next[1];
    }

    /**
     * The candidate FQCNs a token names, or none when it is not a class reference.
     *
     * @param int|string      $id        Current token id.
     * @param string          $text      Current token text.
     * @param int|string|null $previous  Previous token id.
     * @param string          $namespace The file's namespace.
     *
     * @return list<string> Candidate FQCNs.
     */
    private function referencedNames(int|string $id, string $text, int|string|null $previous, string $namespace): array
    {
        if (!$this->isClassReference($id, $previous)) {
            return [];
        }

        return $this->namesFor($text, $id, $namespace);
    }

    /**
     * Whether a token is a name in a position where it can refer to a class.
     *
     * @param int|string      $id       Current token id.
     * @param int|string|null $previous Previous token id.
     *
     * @return bool True for a name outside a member position.
     */
    private function isClassReference(int|string $id, int|string|null $previous): bool
    {
        return in_array($id, self::NAME_TOKENS, true) && !in_array($previous, self::MEMBER_POSITIONS, true);
    }

    /**
     * The candidate FQCNs for one name token: as written, and qualified by the namespace when bare.
     *
     * @param string     $text      The token text.
     * @param int|string $id        The token id.
     * @param string     $namespace The file's namespace.
     *
     * @return list<string> Candidate FQCNs, without a leading `\`.
     */
    private function namesFor(string $text, int|string $id, string $namespace): array
    {
        $name = ltrim($text, '\\');

        if ($name === '') {
            return [];
        }

        if ($id === T_NAME_FULLY_QUALIFIED || $namespace === '' || str_contains($name, '\\')) {
            return [$name];
        }

        return [$name, $namespace . '\\' . $name];
    }
}
