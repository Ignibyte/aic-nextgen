<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\DumpToken;
use AIC\Workflow\Domain\Import\DumpTokenKind;
use AIC\Workflow\Exception\WorkflowRefusal;

use function preg_match;
use function str_replace;
use function strlen;
use function substr;

/**
 * The tokens of a `pg_dump --schema-only` (#318), split into statements at each `;`: words, quoted identifiers,
 * strings, numbers, symbols and dollar-quoted bodies. Whitespace, `--` comments and psql's `\restrict` lines go.
 */
final readonly class DumpLexer
{
    /** Each token's kind and pattern, tried in this order: a comment before the `-` symbol, a string before a word. */
    private const array PATTERNS = [
        ['space', '/\\G\\s+/'],
        ['comment', '/\\G--[^\\n]*/'],
        ['meta', '/\\G\\\\[^\\n]*/'],
        ['body', '/\\G(\\$[A-Za-z_]*\\$).*?\\1/s'],
        ['text', "/\\G[Ee]?'(?:[^']|'')*'/"],
        ['quoted', '/\\G"(?:[^"]|"")*"/'],
        ['number', '/\\G\\d+(?:\\.\\d+)?/'],
        ['word', '/\\G[A-Za-z_][A-Za-z0-9_$]*/'],
        ['symbol', '/\\G(?:::|<>|<=|>=|!=|\\|\\||[~!@#^&|?][~!@#^&|?<>=*]*|[(),.;:=<>+\\-*\\/\\[\\]%])/'],
    ];

    /**
     * @param string $sql The dump.
     *
     * @throws WorkflowRefusal When the dump holds a character no pg_dump writes outside a string.
     *
     * @return list<list<DumpToken>> Its statements, each its tokens without the `;`.
     */
    public function statements(string $sql): array
    {
        $statements = [];
        $statement = [];

        foreach ($this->tokens($sql) as $dumpToken) {
            if (!$dumpToken->isSymbol(';')) {
                $statement[] = $dumpToken;
                continue;
            }

            $statements[] = $statement;
            $statement = [];
        }

        return $statement === []
            ? $statements
            : [...$statements, $statement];
    }

    /**
     * @param string $sql The dump.
     *
     * @throws WorkflowRefusal When the dump holds a character no pg_dump writes outside a string.
     *
     * @return list<DumpToken> Its tokens.
     */
    private function tokens(string $sql): array
    {
        $tokens = [];
        $offset = 0;

        while ($offset < strlen($sql)) {
            [$kind, $text] = $this->next($sql, $offset);
            $offset += strlen($text);
            $dumpToken = $this->token($kind, $text);

            if (!$dumpToken instanceof DumpToken) {
                continue;
            }

            $tokens[] = $dumpToken;
        }

        return $tokens;
    }

    /**
     * @param string $sql    The dump.
     * @param int    $offset Where the next token starts.
     *
     * @throws WorkflowRefusal When nothing matches there.
     *
     * @return array{string, string} The token's kind and its text.
     */
    private function next(string $sql, int $offset): array
    {
        foreach (self::PATTERNS as [$kind, $pattern]) {
            $text = $this->matched($pattern, $sql, $offset)[0] ?? '';

            if ($text !== '') {
                return [$kind, $text];
            }
        }

        throw new WorkflowRefusal('the dump holds "' . substr($sql, $offset, 24) . '", which no pg_dump --schema-only writes.');
    }

    /**
     * @param non-empty-string $pattern A token's pattern, anchored at the offset with `\G`.
     * @param string           $sql     The dump.
     * @param int              $offset  Where to match.
     *
     * @return array<array-key, string> The match and its captures; empty when it does not match there.
     */
    private function matched(string $pattern, string $sql, int $offset): array
    {
        $matches = [];
        preg_match($pattern, $sql, $matches, 0, $offset);

        return $matches;
    }

    /**
     * @param string $kind The kind.
     * @param string $text The text.
     *
     * @return DumpToken|null The token; null for whitespace, a comment or a psql line.
     */
    private function token(string $kind, string $text): ?DumpToken
    {
        return match ($kind) {
            'body' => new DumpToken(DumpTokenKind::Body, $text),
            'text' => new DumpToken(DumpTokenKind::Text, str_replace("''", "'", substr($text, $text[0] === "'" ? 1 : 2, -1))),
            'quoted' => new DumpToken(DumpTokenKind::Quoted, str_replace('""', '"', substr($text, 1, -1))),
            'number' => new DumpToken(DumpTokenKind::Number, $text),
            'word' => new DumpToken(DumpTokenKind::Word, $text),
            'symbol' => new DumpToken(DumpTokenKind::Symbol, $text),
            default => null,
        };
    }
}
