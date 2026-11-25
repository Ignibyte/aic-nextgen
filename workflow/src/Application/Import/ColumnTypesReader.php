<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\DumpToken;
use AIC\Workflow\Domain\Import\ImportedType;
use AIC\Workflow\Exception\UnsupportedSql;

use function array_slice;
use function implode;
use function preg_match;

/**
 * A column's type as pg_dump spells it, read into the `ColumnTypes` call that declares it (#318), or the one it is
 * translated to with a note (#339): a timestamp's precision below 6 is its argument.
 */
final readonly class ColumnTypesReader
{
    private const array STOPS = [
        'default',
        'not',
        'null',
        'generated',
        'collate',
        'constraint',
        'check',
        'references',
        'primary',
        'unique',
    ];

    /** Each spelling's `ColumnTypes` method and, for a translation, the note the import prints (#339). */
    private const array TYPES = [
        '/^bigint$/' => ['bigInteger', ''],
        '/^boolean$/' => ['boolean', ''],
        '/^character varying\\((\\d+)\\)$/' => ['string', ''],
        '/^date$/' => ['date', ''],
        '/^integer$/' => ['integer', ''],
        '/^json$/' => ['jsonb', ' is json; it becomes jsonb(), the JSON AIC reads, which keeps no whitespace or repeated key.'],
        '/^jsonb$/' => ['jsonb', ''],
        '/^numeric\\((\\d+),(\\d+)\\)$/' => ['numeric', ''],
        '/^smallint$/' => ['integer', ' is smallint; it becomes integer(), since Schema declares no smallint.'],
        '/^text$/' => ['text', ''],
        '/^timestamp(?:\\(([0-5])\\)|\\(6\\))? without time zone$/' => [
            'timestampTz',
            ' is a timestamp without time zone; it becomes timestampTz(), a point in time with its offset: move its values'
            . ' as times in the zone they were written in.',
        ],
        '/^timestamp(?:\\(([0-5])\\)|\\(6\\))? with time zone$/' => ['timestampTz', ''],
        '/^uuid$/' => ['uuid', ''],
    ];

    /**
     * @param TokenCursor $tokenCursor At the type, which is read.
     *
     * @throws UnsupportedSql When Schema has no such type.
     *
     * @return ImportedType The type.
     */
    public function read(TokenCursor $tokenCursor): ImportedType
    {
        $spelled = $this->spelled($tokenCursor);

        foreach (self::TYPES as $pattern => [$method, $note]) {
            $matches = [];

            if (preg_match($pattern, $spelled, $matches) === 1) {
                return new ImportedType($method, implode(', ', array_slice($matches, 1)), $spelled, $note);
            }
        }

        throw new UnsupportedSql('the type ' . $spelled . ' has no Schema declaration');
    }

    /**
     * @param TokenCursor $tokenCursor At the type.
     *
     * @throws UnsupportedSql When a group does not close.
     *
     * @return string The type's words, its arguments attached: `character varying(13)`.
     */
    private function spelled(TokenCursor $tokenCursor): string
    {
        $spelled = '';
        $dumpToken = $tokenCursor->peek();

        while ($dumpToken instanceof DumpToken && $this->partOfType($tokenCursor, $dumpToken)) {
            $spelled .= $this->piece($tokenCursor, $dumpToken, $spelled);
            $dumpToken = $tokenCursor->peek();
        }

        return $spelled;
    }

    /**
     * @param TokenCursor $tokenCursor At the token.
     * @param DumpToken   $dumpToken   The token.
     *
     * @return bool Whether the token continues the type.
     */
    private function partOfType(TokenCursor $tokenCursor, DumpToken $dumpToken): bool
    {
        return $dumpToken->isSymbol('(') || $dumpToken->isSymbol('[') || $dumpToken->isSymbol('.')
            || ($dumpToken->isName() && !$tokenCursor->nextIs(self::STOPS));
    }

    /**
     * @param TokenCursor $tokenCursor At the token.
     * @param DumpToken   $dumpToken   The token.
     * @param string      $spelled     The type so far.
     *
     * @throws UnsupportedSql When a group does not close.
     *
     * @return string The token's part of the type, read.
     */
    private function piece(TokenCursor $tokenCursor, DumpToken $dumpToken, string $spelled): string
    {
        if ($dumpToken->isSymbol('(')) {
            return '(' . $this->arguments($tokenCursor->group()) . ')';
        }

        if ($dumpToken->isSymbol('[')) {
            $tokenCursor->expect('[', ']');

            return '[]';
        }

        $text = $tokenCursor->next()->text;

        return $spelled === '' || $dumpToken->isSymbol('.') || $spelled[-1] === '.'
            ? $text
            : ' ' . $text;
    }

    /**
     * @param TokenCursor $tokenCursor The arguments' tokens.
     *
     * @throws UnsupportedSql When an argument is more than one token.
     *
     * @return string The arguments, joined by commas.
     */
    private function arguments(TokenCursor $tokenCursor): string
    {
        $arguments = [];

        foreach ($tokenCursor->items() as $item) {
            $arguments[] = $item->next()->text;
            $item->end();
        }

        return implode(',', $arguments);
    }
}
