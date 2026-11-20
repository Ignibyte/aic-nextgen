<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\DumpToken;
use AIC\Workflow\Domain\Import\ImportedIndex;
use AIC\Workflow\Exception\UnsupportedSql;

use function array_unique;
use function array_values;
use function count;
use function strtolower;

/**
 * A `CREATE [UNIQUE] INDEX` of a schema dump (#318), read into a btree index (plain, unique or partial) or an
 * `IndexMethod` one. The index's name is AIC's own once declared.
 */
final readonly class IndexReader
{
    private const array METHODS = ['brin' => 'Brin', 'hash' => 'Hash', 'gist' => 'Gist', 'gin' => 'Gin'];

    private const array CLASSES = ['gin:gin_trgm_ops' => 'Trigram', 'gin:jsonb_path_ops' => 'JsonPath'];

    /**
     * @param ExpressionReader $expressionReader Reads a partial index's rows.
     */
    public function __construct(
        private ExpressionReader $expressionReader = new ExpressionReader(),
    ) {
    }

    /**
     * @param TokenCursor $tokenCursor After `CREATE [UNIQUE] INDEX`.
     * @param bool        $unique      Whether it said `UNIQUE`.
     * @param DumpFacts   $dumpFacts   What the dump has said so far; the table is changed in it.
     *
     * @throws UnsupportedSql When the index has no Schema declaration.
     */
    public function read(TokenCursor $tokenCursor, bool $unique, DumpFacts $dumpFacts): void
    {
        $tokenCursor->name();
        $tokenCursor->expect('on');
        $tokenCursor->accept('only');

        $importedTable = $dumpFacts->table($tokenCursor->table());
        $tokenCursor->expect('using');
        $using = strtolower($tokenCursor->name());
        [$columns, $classes] = $this->columns($tokenCursor->group());
        $where = $tokenCursor->accept('where')
            ? $this->expressionReader->predicate($tokenCursor->group())
            : null;
        $tokenCursor->end();
        $method = $this->method($using, $classes, $unique || $where !== null);
        $dumpFacts->put($importedTable->withIndex(new ImportedIndex($columns, $method, $unique, $where)));
    }

    /**
     * @param TokenCursor $tokenCursor The index's columns.
     *
     * @throws UnsupportedSql When one is an expression or has an order.
     *
     * @return array{list<string>, list<string>} The columns, and each one's operator class (empty for the default).
     */
    private function columns(TokenCursor $tokenCursor): array
    {
        $columns = [];
        $classes = [];

        foreach ($tokenCursor->items() as $item) {
            if (!$this->isColumn($item)) {
                throw new UnsupportedSql('an index on an expression (' . $item->rest() . ') has no Schema declaration');
            }

            $columns[] = $item->name();
            $classes[] = $this->operatorClass($item);
        }

        return [$columns, $classes];
    }

    /**
     * @param TokenCursor $tokenCursor After an index column.
     *
     * @throws UnsupportedSql When an order or anything else follows.
     *
     * @return string The operator class without its schema; empty for none.
     */
    private function operatorClass(TokenCursor $tokenCursor): string
    {
        if ($tokenCursor->atEnd()) {
            return '';
        }

        $name = $tokenCursor->name();
        $name = $tokenCursor->accept('.')
            ? $tokenCursor->name()
            : $name;

        $tokenCursor->end();

        return $name;
    }

    /**
     * @param TokenCursor $tokenCursor An index item.
     *
     * @return bool Whether it starts with a column's name rather than an expression.
     */
    private function isColumn(TokenCursor $tokenCursor): bool
    {
        $first = $tokenCursor->peek();
        $second = $tokenCursor->peek(1);

        return $first instanceof DumpToken && $first->isName() && (!$second instanceof DumpToken || !$second->isSymbol('('));
    }

    /**
     * @param string       $using   The access method.
     * @param list<string> $classes Each column's operator class.
     * @param bool         $btree   Whether the index is unique or partial, which only a btree index may be.
     *
     * @throws UnsupportedSql When the method or a class has no `IndexMethod`.
     *
     * @return string The `IndexMethod` case; empty for a btree.
     */
    private function method(string $using, array $classes, bool $btree): string
    {
        $distinct = array_values(array_unique($classes));
        $class = $distinct[0] ?? '';

        if ($using === 'btree' && $distinct === ['']) {
            return '';
        }

        $method = count($distinct) === 1 && !$btree
            ? $this->named($using, $class)
            : '';

        return $method !== ''
            ? $method
            : throw new UnsupportedSql($this->refusal($using, $class, $btree));
    }

    /**
     * @param string $using The access method.
     * @param string $class The operator class; empty for the default.
     * @param bool   $btree Whether the index is unique or partial.
     *
     * @return string Why the index has no `IndexMethod`.
     */
    private function refusal(string $using, string $class, bool $btree): string
    {
        $kind = $btree
            ? 'unique or partial '
            : '';
        $with = $class === ''
            ? ''
            : ' with ' . $class;

        return 'a ' . $kind . $using . ' index' . $with . ' has no Schema declaration';
    }

    /**
     * @param string $using The access method.
     * @param string $class The operator class every column has; empty for the default.
     *
     * @return string The `IndexMethod` case; empty for none.
     */
    private function named(string $using, string $class): string
    {
        return $class === '' ? self::METHODS[$using] ?? '' : self::CLASSES[$using . ':' . $class] ?? '';
    }
}
