<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\CodeCall;
use AIC\Workflow\Domain\Import\CodeNode;
use AIC\Workflow\Domain\Import\ColumnKey;
use AIC\Workflow\Domain\Import\ExpressionMethod;
use AIC\Workflow\Domain\Import\ImportedCheck;
use AIC\Workflow\Domain\Import\ImportedColumn;
use AIC\Workflow\Domain\Import\ImportedForeignKey;
use AIC\Workflow\Domain\Import\ImportedIndex;
use AIC\Workflow\Domain\Import\ImportedTable;

use function array_map;
use function count;
use function implode;
use function lcfirst;
use function str_replace;
use function strlen;
use function ucfirst;
use function ucwords;

/**
 * The `TableBuilder` chain lines an imported table's migration holds (#318): one or two per column (`->nullable()`)
 * and one per key, constraint and index. An expression rides on its line when the line fits, and becomes an
 * `ExpressionMethod` the line calls when it does not.
 */
final readonly class MigrationLines
{
    private const string CHAIN = '            ->';

    /**
     * @param CallPrinter $callPrinter Prints the expressions.
     * @param PhpLiteral  $phpLiteral  Writes the names.
     */
    public function __construct(
        private CallPrinter $callPrinter = new CallPrinter(),
        private PhpLiteral $phpLiteral = new PhpLiteral(),
    ) {
    }

    /**
     * @param ImportedColumn $importedColumn A column.
     *
     * @return array{list<string>, list<ExpressionMethod>} Its lines, and the method its term needs.
     */
    public function column(ImportedColumn $importedColumn): array
    {
        $name = $this->phpLiteral->string($importedColumn->name);
        $nullable = $importedColumn->nullable
            ? [self::CHAIN . 'nullable()']
            : [];
        $typed = $this->typed($importedColumn);
        $wrapped = match (true) {
            $importedColumn->generated instanceof CodeNode => ['generated', $importedColumn->generated, 'Term', 'The generated `%s`'],
            $importedColumn->default instanceof CodeNode => ['defaulted', $importedColumn->default, 'Default', 'The default of `%s`'],
            default => null,
        };

        if ($wrapped === null) {
            return [[$this->plain($importedColumn, $name, $typed), ...$nullable], []];
        }

        [$line, $methods] = $this->wrapped($importedColumn->name, $name . ', $columnTypes->' . $wrapped[0] . '(' . $typed, $wrapped);

        return [[$line, ...$nullable], $methods];
    }

    /**
     * @param ImportedTable $importedTable A table.
     *
     * @return array{list<string>, list<ExpressionMethod>} Its key, constraint and index lines, and the methods their
     *                                                     predicates need.
     */
    public function constraints(ImportedTable $importedTable): array
    {
        $lines = $this->keys($importedTable);
        $methods = [];

        $ridden = [...array_map($this->check(...), $importedTable->checks), ...array_map($this->index(...), $importedTable->indexes)];

        foreach ($ridden as [$line, $more]) {
            $lines[] = $line;
            $methods = [...$methods, ...$more];
        }

        return [$lines, $methods];
    }

    /**
     * @param string                                  $column  The column.
     * @param string                                  $opening Its name and type as PHP, up to the term.
     * @param array{string, CodeNode, string, string} $wrapped The `ColumnTypes` method, the term, the method suffix and
     *                                                         what the term is.
     *
     * @return array{string, list<ExpressionMethod>} The column's line, and the method its term needs.
     */
    private function wrapped(string $column, string $opening, array $wrapped): array
    {
        [, $codeNode, $suffix, $about] = $wrapped;
        $expressionMethod = new ExpressionMethod($this->method($column, $suffix), 'Term', $codeNode, str_replace('%s', $column, $about));

        return $this->riding(self::CHAIN . 'column(' . $opening . ', %s))', $expressionMethod);
    }

    /**
     * @param ImportedTable $importedTable A table.
     *
     * @return list<string> Its primary key, unique constraint and foreign key lines.
     */
    private function keys(ImportedTable $importedTable): array
    {
        $lines = $importedTable->primary === []
            ? []
            : [self::CHAIN . 'primary(' . $this->names($importedTable->primary) . ')'];

        foreach ($importedTable->uniques as $unique) {
            $lines[] = self::CHAIN . 'unique(' . $this->names($unique) . ')';
        }

        foreach ($importedTable->foreignKeys as $importedForeignKey) {
            $lines[] = $this->foreignKey($importedForeignKey);
        }

        return $lines;
    }

    /**
     * @param ImportedForeignKey $importedForeignKey A foreign key.
     *
     * @return string Its line: `foreignKey()` for one column, `references()` for several (#344).
     */
    private function foreignKey(ImportedForeignKey $importedForeignKey): string
    {
        $single = count($importedForeignKey->columns) === 1;
        $names = array_map(
            fn (array $columns): string => $single ? $this->phpLiteral->string(implode('', $columns)) : $this->names($columns),
            [$importedForeignKey->columns, $importedForeignKey->foreignColumns],
        );

        $table = $this->phpLiteral->string($importedForeignKey->table);

        return self::CHAIN . ($single ? 'foreignKey(' : 'references(') . $names[0] . ', ' . $table . ', ' . $names[1]
            . ', OnDelete::' . $importedForeignKey->onDelete . ')';
    }

    /**
     * @param ImportedCheck $importedCheck A check.
     *
     * @return array{string, list<ExpressionMethod>} Its line, and the method its predicate needs.
     */
    private function check(ImportedCheck $importedCheck): array
    {
        $name = $importedCheck->name;

        return $this->riding(
            self::CHAIN . 'check(' . $this->phpLiteral->string($name) . ', %s)',
            new ExpressionMethod($this->method($name, 'Check'), 'Predicate', $importedCheck->predicate, 'The `' . $name . '` check'),
        );
    }

    /**
     * @param ImportedIndex $importedIndex An index.
     *
     * @return array{string, list<ExpressionMethod>} Its line, and the method its rows need.
     */
    private function index(ImportedIndex $importedIndex): array
    {
        $columns = $this->names($importedIndex->columns);

        if ($importedIndex->method !== '') {
            return [self::CHAIN . 'indexUsing(' . $columns . ', IndexMethod::' . $importedIndex->method . ')', []];
        }

        $kind = match (true) {
            $importedIndex->nullsEqual => 'uniqueWithNulls(',
            $importedIndex->unique => 'unique(',
            default => 'index(',
        };

        return $importedIndex->where === null
            ? [self::CHAIN . $kind . $columns . ')', []]
            : $this->partial($importedIndex, $importedIndex->where, $columns);
    }

    /**
     * @param ImportedIndex $importedIndex A partial btree index.
     * @param CodeCall      $codeCall      The rows it covers.
     * @param string        $columns       Its columns, as a PHP list.
     *
     * @return array{string, list<ExpressionMethod>} Its line, and the method its rows need.
     */
    private function partial(ImportedIndex $importedIndex, CodeCall $codeCall, string $columns): array
    {
        $kind = $importedIndex->unique
            ? 'unique'
            : 'index';
        $unique = $importedIndex->unique
            ? 'unique '
            : '';
        $about = 'The rows the ' . $unique . 'index on ' . implode(', ', $importedIndex->columns) . ' covers';
        $method = $this->method(implode('_', $importedIndex->columns), ucfirst($kind) . 'Rows');

        $expressionMethod = new ExpressionMethod($method, 'Predicate', $codeCall, $about);

        return $this->riding(self::CHAIN . $kind . 'Where(' . $columns . ', %s)', $expressionMethod);
    }

    /**
     * @param string           $line             A line with `%s` where the expression goes.
     * @param ExpressionMethod $expressionMethod The expression, as the method it becomes when it does not fit.
     *
     * @return array{string, list<ExpressionMethod>} The line, and the method when it is used.
     */
    private function riding(string $line, ExpressionMethod $expressionMethod): array
    {
        $inline = str_replace('%s', $this->callPrinter->inline($expressionMethod->codeNode), $line);

        if (strlen($inline) + 1 <= CallPrinter::WIDTH) {
            return [$inline, []];
        }

        return [str_replace('%s', '$this->' . $expressionMethod->name . '($expressions)', $line), [$expressionMethod]];
    }

    /**
     * @param ImportedColumn $importedColumn A column.
     *
     * @return string Its `ColumnTypes` call.
     */
    private function typed(ImportedColumn $importedColumn): string
    {
        return match (true) {
            $importedColumn->columnKey === ColumnKey::UuidKey => '$columnTypes->uuidKey()',
            $importedColumn->choices !== [] => '$columnTypes->choice(' . $this->names($importedColumn->choices) . ')',
            default => '$columnTypes->' . $importedColumn->importedType->method . '(' . $importedColumn->importedType->arguments . ')',
        };
    }

    /**
     * @param ImportedColumn $importedColumn A column with no default and no generated term.
     * @param string         $name           Its name, as PHP.
     * @param string         $typed          Its `ColumnTypes` call.
     *
     * @return string Its line.
     */
    private function plain(ImportedColumn $importedColumn, string $name, string $typed): string
    {
        $importedType = $importedColumn->importedType;

        $numbered = $importedColumn->columnKey !== ColumnKey::None && $importedColumn->columnKey !== ColumnKey::UuidKey;
        $arguments = $importedType->arguments === ''
            ? ''
            : ', ' . $importedType->arguments;

        return match (true) {
            $numbered => self::CHAIN . 'identity(' . $name . ')',
            $importedType->onBuilder() && $importedColumn->choices === [] => self::CHAIN . $importedType->method
                . '(' . $name . $arguments . ')',
            default => self::CHAIN . 'column(' . $name . ', ' . $typed . ')',
        };
    }

    /**
     * @param list<string> $names Names.
     *
     * @return string Them as a PHP list.
     */
    private function names(array $names): string
    {
        return '[' . implode(', ', array_map($this->phpLiteral->string(...), $names)) . ']';
    }

    /**
     * @param string $name   A snake-case name.
     * @param string $suffix What the method returns, such as `Check`.
     *
     * @return string The method's name: `pagesBoundedCheck`.
     */
    private function method(string $name, string $suffix): string
    {
        return lcfirst(str_replace('_', '', ucwords($name, '_'))) . $suffix;
    }
}
