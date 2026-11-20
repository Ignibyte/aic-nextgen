<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\DumpToken;
use AIC\Workflow\Exception\UnsupportedSql;

use function array_slice;
use function array_values;
use function count;
use function implode;
use function in_array;
use function strtolower;

/**
 * A reader's place in one statement's tokens (#318). It moves forward only; the readers ask it for words, names and
 * parenthesized groups, and it throws `UnsupportedSql` naming what it met when the statement goes another way.
 */
final class TokenCursor
{
    private int $position = 0;

    /**
     * @param list<DumpToken> $tokens The tokens.
     */
    public function __construct(
        private readonly array $tokens,
    ) {
    }

    /**
     * @param int $ahead How far past the next token to look.
     *
     * @return DumpToken|null The token there; null past the end.
     */
    public function peek(int $ahead = 0): ?DumpToken
    {
        return $this->tokens[$this->position + $ahead] ?? null;
    }

    /**
     * @return bool Whether every token is read.
     */
    public function atEnd(): bool
    {
        return $this->position >= count($this->tokens);
    }

    /**
     * @throws UnsupportedSql When the statement ends.
     *
     * @return DumpToken The next token, read.
     */
    public function next(): DumpToken
    {
        $dumpToken = $this->peek() ?? throw new UnsupportedSql('a statement ends where more was expected');
        $this->position += 1;

        return $dumpToken;
    }

    /**
     * @param string ...$words Words or symbols, in order.
     *
     * @return bool Whether the next tokens are those; they are read when they are.
     */
    public function accept(string ...$words): bool
    {
        foreach (array_values($words) as $index => $word) {
            $dumpToken = $this->peek($index);

            if (!$dumpToken instanceof DumpToken || (!$dumpToken->isWord($word) && !$dumpToken->isSymbol($word))) {
                return false;
            }
        }

        $this->position += count($words);

        return true;
    }

    /**
     * @param string ...$words Words or symbols, in order.
     *
     * @throws UnsupportedSql When the next tokens are anything else.
     */
    public function expect(string ...$words): void
    {
        if (!$this->accept(...$words)) {
            throw new UnsupportedSql('expected ' . implode(' ', $words) . ' at "' . $this->rest() . '"');
        }
    }

    /**
     * @throws UnsupportedSql When the next token names nothing.
     *
     * @return string The name the next token gives, read.
     */
    public function name(): string
    {
        $dumpToken = $this->next();

        return $dumpToken->isName()
            ? $dumpToken->text
            : throw new UnsupportedSql('expected a name at "' . $dumpToken->text . ' ' . $this->rest() . '"');
    }

    /**
     * @throws UnsupportedSql When the name is in another schema than public, or unqualified.
     *
     * @return string A `public.<name>`'s name, read.
     */
    public function table(): string
    {
        $schema = $this->name();
        $this->expect('.');
        $name = $this->name();

        return $schema === 'public'
            ? $name
            : throw new UnsupportedSql('the schema ' . $schema . ' holds ' . $name . '; Schema declares tables in public only');
    }

    /**
     * @throws UnsupportedSql When the next token is not `(`, or the group does not close.
     *
     * @return self The tokens inside the parentheses that open next, read with them.
     */
    public function group(): self
    {
        $this->expect('(');
        $start = $this->position;
        $depth = 1;

        while ($depth > 0) {
            $dumpToken = $this->next();
            $depth += match (true) {
                $dumpToken->isSymbol('(') => 1,
                $dumpToken->isSymbol(')') => -1,
                default => 0,
            };
        }

        return new self(array_slice($this->tokens, $start, $this->position - $start - 1));
    }

    /**
     * @throws UnsupportedSql When a group does not close.
     *
     * @return list<self> The tokens between the commas outside any parentheses, each read to the end.
     */
    public function items(): array
    {
        $items = [];
        $item = [];

        while (!$this->atEnd()) {
            if ($this->accept(',')) {
                $items[] = new self($item);
                $item = [];
                continue;
            }

            $item = [...$item, ...$this->piece()];
        }

        return [...$items, new self($item)];
    }

    /**
     * @throws UnsupportedSql When the list holds anything but names.
     *
     * @return list<string> The names inside the parentheses that open next, read.
     */
    public function names(): array
    {
        $names = [];

        foreach ($this->group()->items() as $tokenCursor) {
            $names[] = $tokenCursor->name();
            $tokenCursor->end();
        }

        return $names;
    }

    /**
     * @throws UnsupportedSql When tokens are left.
     */
    public function end(): void
    {
        if (!$this->atEnd()) {
            throw new UnsupportedSql('"' . $this->rest() . '" has no Schema declaration');
        }
    }

    /**
     * @param list<string> $words Words.
     *
     * @return bool Whether the next token is one of those words; not read.
     */
    public function nextIs(array $words): bool
    {
        $dumpToken = $this->peek();

        return $dumpToken instanceof DumpToken && $dumpToken->isName() && in_array(strtolower($dumpToken->text), $words, true);
    }

    /**
     * @return string The tokens left, for a message.
     */
    public function rest(): string
    {
        $texts = [];

        foreach (array_slice($this->tokens, $this->position, 12) as $dumpToken) {
            $texts[] = $dumpToken->text;
        }

        return implode(' ', $texts);
    }

    /**
     * @throws UnsupportedSql When a group does not close.
     *
     * @return list<DumpToken> The next token, or the whole parenthesized group it opens.
     */
    private function piece(): array
    {
        $start = $this->position;

        $dumpToken = $this->peek();

        if ($dumpToken instanceof DumpToken && $dumpToken->isSymbol('(')) {
            $this->group();

            return array_slice($this->tokens, $start, $this->position - $start);
        }

        return [$this->next()];
    }
}
