<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

use function strtolower;

/**
 * One token of a schema dump (#318): a word, a quoted identifier, a string, a number, a symbol or a dollar-quoted
 * body, with its text unquoted.
 */
final readonly class DumpToken
{
    /**
     * @param DumpTokenKind $dumpTokenKind What it is.
     * @param string        $text          Its text: a string or identifier without its quotes.
     */
    public function __construct(
        public DumpTokenKind $dumpTokenKind,
        public string $text,
    ) {
    }

    /**
     * @param string $word A keyword, in any case.
     *
     * @return bool Whether the token is that unquoted word.
     */
    public function isWord(string $word): bool
    {
        return $this->dumpTokenKind === DumpTokenKind::Word && strtolower($this->text) === strtolower($word);
    }

    /**
     * @param string $symbol A symbol, such as `(` or `::`.
     *
     * @return bool Whether the token is that symbol.
     */
    public function isSymbol(string $symbol): bool
    {
        return $this->dumpTokenKind === DumpTokenKind::Symbol && $this->text === $symbol;
    }

    /**
     * @return bool Whether it names something: a word or a quoted identifier.
     */
    public function isName(): bool
    {
        return $this->dumpTokenKind === DumpTokenKind::Word || $this->dumpTokenKind === DumpTokenKind::Quoted;
    }
}
