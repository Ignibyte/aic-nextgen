<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Import\ExpressionMethod;
use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Exception\UnsupportedSql;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function count;
use function implode;
use function lcfirst;
use function max;
use function str_contains;
use function str_repeat;
use function str_replace;
use function strlen;
use function substr_count;
use function ucwords;

/**
 * One imported table's migration (#318): `describe()` creates the table and hands it to helpers of at most 14 chain
 * lines (columns, then keys, constraints and indexes), and each expression too long for its line is a method of its
 * own, so every function fits PHPCS's 20 lines. A table whose class would pass PHPMD's 15 methods or 300 lines is
 * refused, named.
 */
final readonly class MigrationWriter
{
    private const int CHAIN_LINES = 14;

    private const string CONTRACTS = 'AIC\\Schema\\Contracts\\';

    /** Each import with what in the class's methods needs it, in the sorted order PHPCS holds; an empty marker always. */
    private const array USES = [
        ['Arithmetic', 'Arithmetic::'],
        ['ColumnTypes', 'ColumnTypes $'],
        ['Expressions', 'Expressions $'],
        ['IndexMethod', 'IndexMethod::'],
        ['Migration', ''],
        ['OnDelete', 'OnDelete::'],
        ['Operator', 'Operator::'],
        ['Predicate', '): Predicate'],
        ['Schema', ''],
        ['TableBuilder', 'TableBuilder $'],
        ['Term', '): Term'],
    ];

    /**
     * @param MigrationLines $migrationLines The chain lines.
     * @param CallPrinter    $callPrinter    Prints the long expressions.
     * @param MakeTemplates  $makeTemplates  Renders the class.
     */
    public function __construct(
        private MigrationLines $migrationLines = new MigrationLines(),
        private CallPrinter $callPrinter = new CallPrinter(),
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param ImportedTable $importedTable The table.
     * @param string        $namespace     The module's namespace.
     * @param string        $class         The migration's class.
     * @param string        $version       Its version.
     *
     * @throws UnsupportedSql  When the class would pass PHPMD's limits, or an expression one method's 20 lines.
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The migration's PHP.
     */
    public function source(ImportedTable $importedTable, string $namespace, string $class, string $version): string
    {
        [$columns, $columnMethods] = $this->columns($importedTable);
        [$constraints, $constraintMethods] = $this->migrationLines->constraints($importedTable);
        $stem = lcfirst(str_replace('_', '', ucwords($importedTable->name, '_')));
        $helpers = [...$this->helpers($stem . 'Columns', $columns), ...$this->helpers($stem . 'Constraints', $this->grouped($constraints))];
        $expressions = array_map($this->expression(...), [...$columnMethods, ...$constraintMethods]);
        $methods = implode('', [...array_map(static fn (array $helper): string => $helper[1], $helpers), ...$expressions]);

        $count = count($helpers) + count($expressions) + 2;

        return $this->checked($count, $this->makeTemplates->render('schema-import', 'migration.php.tpl', [
            'class' => $class,
            'describe' => $this->describe($importedTable, $helpers),
            'methods' => $methods,
            'namespace' => $namespace,
            'table' => $importedTable->name,
            'uses' => $this->uses($methods),
            'version' => $version,
        ]));
    }

    /**
     * @param int    $methods How many methods the class has.
     * @param string $source  The class.
     *
     * @throws UnsupportedSql When it passes PHPMD's 15 methods or 300 lines.
     *
     * @return string The class.
     */
    private function checked(int $methods, string $source): string
    {
        if ($methods > 15) {
            throw new UnsupportedSql('its migration would need ' . $methods . ' methods, more than PHPMD allows (15)');
        }

        return substr_count($source, "\n") > 300
            ? throw new UnsupportedSql('its migration would be longer than PHPMD allows (300 lines)')
            : $source;
    }

    /**
     * @param ImportedTable $importedTable The table.
     *
     * @return array{list<list<string>>, list<ExpressionMethod>} Its column lines, grouped by column, and their methods.
     */
    private function columns(ImportedTable $importedTable): array
    {
        $groups = [];
        $methods = [];

        foreach ($importedTable->columns as $importedColumn) {
            [$groups[], $more] = $this->migrationLines->column($importedColumn);
            $methods = [...$methods, ...$more];
        }

        return [$groups, $methods];
    }

    /**
     * @param list<string> $lines Lines.
     *
     * @return list<list<string>> Each line a group of its own.
     */
    private function grouped(array $lines): array
    {
        return array_map(static fn (string $line): array => [$line], $lines);
    }

    /**
     * @param string             $name   `<table>Columns` or `<table>Constraints`, named for the table so two
     *                                   tables' migrations are no copy-paste clone of each other.
     * @param list<list<string>> $groups The chain lines, in groups kept together.
     *
     * @return list<array{string, string}> Each helper's call from `describe()` and its PHP.
     */
    private function helpers(string $name, array $groups): array
    {
        $chunks = $this->chunks($groups);
        $helpers = [];

        foreach ($chunks as $index => $lines) {
            $helpers[] = $this->helper(count($chunks) === 1 ? $name : $name . 'Part' . ($index + 1), $lines);
        }

        return $helpers;
    }

    /**
     * @param list<list<string>> $groups The chain lines, in groups kept together.
     *
     * @return list<list<string>> The lines in chunks of at most 14, no group split.
     */
    private function chunks(array $groups): array
    {
        $chunks = [];
        $chunk = [];

        foreach ($groups as $group) {
            if (count($chunk) + count($group) > self::CHAIN_LINES) {
                $chunks[] = $chunk;
                $chunk = [];
            }

            $chunk = [...$chunk, ...$group];
        }

        return $chunk === []
            ? $chunks
            : [...$chunks, $chunk];
    }

    /**
     * @param string       $name  The helper.
     * @param list<string> $lines Its chain lines.
     *
     * @return array{string, string} Its call from `describe()` and its PHP.
     */
    private function helper(string $name, array $lines): array
    {
        $body = implode("\n", $lines);
        $parameters = [['TableBuilder', 'tableBuilder', 'The table.', '$tableBuilder']];
        $parameters = str_contains($body, '$columnTypes')
            ? [...$parameters, ['ColumnTypes', 'columnTypes', 'Its column types.', '$schema->types()']]
            : $parameters;
        $parameters = str_contains($body, '$expressions')
            ? [...$parameters, ['Expressions', 'expressions', 'Its terms and predicates.', '$schema->expressions()']]
            : $parameters;

        $signature = implode(', ', array_map(static fn (array $parameter): string => $parameter[0] . ' $' . $parameter[1], $parameters));
        $arguments = implode(', ', array_map(static fn (array $parameter): string => $parameter[3], $parameters));
        $method = $this->signature($name, $signature, $parameters) . "        \$tableBuilder\n" . $body . ";\n    }\n";

        return ['        $this->' . $name . '(' . $arguments . ');', "\n" . $this->docblock($parameters, '') . $method];
    }

    /**
     * @param string                                      $name       The helper.
     * @param string                                      $signature  Its parameters on one line.
     * @param list<array{string, string, string, string}> $parameters Each parameter's type and name.
     *
     * @return string Its signature and opening brace: on one line up to 120 columns, one parameter a line past it, as
     *                PHPCS holds.
     */
    private function signature(string $name, string $signature, array $parameters): string
    {
        $line = '    private function ' . $name . '(' . $signature . '): void';

        if (strlen($line) <= 120) {
            return $line . "\n    {\n";
        }

        $lines = array_map(static fn (array $parameter): string => '        ' . $parameter[0] . ' $' . $parameter[1] . ",\n", $parameters);

        return '    private function ' . $name . "(\n" . implode('', $lines) . "    ): void {\n";
    }

    /**
     * @param ExpressionMethod $expressionMethod An expression too long for its line.
     *
     * @throws UnsupportedSql When its body passes 18 lines.
     *
     * @return string Its method's PHP.
     */
    private function expression(ExpressionMethod $expressionMethod): string
    {
        $lines = $this->callPrinter->lines($expressionMethod->codeNode, '        ', 'return ', ';');

        if (count($lines) > 18) {
            throw new UnsupportedSql(lcfirst($expressionMethod->about) . ' is too long for one method (' . count($lines) . ' lines)');
        }

        $returns = $expressionMethod->returns;
        $parameter = ['Expressions', 'expressions', "The table's terms and predicates.", ''];
        $docblock = $this->docblock([$parameter], $returns . ' ' . $expressionMethod->about . '.');

        $signature = '    private function ' . $expressionMethod->name . '(Expressions $expressions): ' . $returns;

        return "\n" . $docblock . $signature . "\n    {\n"
            . implode("\n", $lines) . "\n    }\n";
    }

    /**
     * @param list<array{string, string, string, string}> $parameters Each parameter's type, name and description.
     * @param string                                      $returns    The `@return` text; empty for none.
     *
     * @return string The docblock, its `@param` lines aligned.
     */
    private function docblock(array $parameters, string $returns): string
    {
        $type = 0;
        $name = 0;

        foreach ($parameters as [$parameterType, $parameterName]) {
            $type = max($type, strlen($parameterType));
            $name = max($name, strlen($parameterName) + 1);
        }

        $lines = "    /**\n";

        foreach ($parameters as [$parameterType, $parameterName, $description]) {
            $lines .= '     * @param ' . $parameterType . str_repeat(' ', $type - strlen($parameterType)) . ' $' . $parameterName
                . str_repeat(' ', $name - strlen($parameterName) - 1) . ' ' . $description . "\n";
        }

        $lines .= "     *\n     * @throws InvalidArgumentException When a declaration is malformed.\n";

        return $lines . ($returns === '' ? '' : "     *\n     * @return " . $returns . "\n") . "     */\n";
    }

    /**
     * @param ImportedTable               $importedTable The table.
     * @param list<array{string, string}> $helpers       The helpers.
     *
     * @return string `describe()`'s body.
     */
    private function describe(ImportedTable $importedTable, array $helpers): string
    {
        $lines = $importedTable->needsTrigrams()
            ? ["        \$schema->extension('pg_trgm');"]
            : [];
        $unlogged = $importedTable->unlogged
            ? '->unlogged()'
            : '';
        $lines[] = "        \$tableBuilder = \$schema->create('" . $importedTable->name . "')" . $unlogged . ';';

        return implode("\n", [...$lines, ...array_map(static fn (array $helper): string => $helper[0], $helpers)]);
    }

    /**
     * @param string $methods The class's private methods.
     *
     * @return string The imports the class needs, sorted.
     */
    private function uses(string $methods): string
    {
        $uses = [];

        foreach (self::USES as [$class, $marker]) {
            if (!str_contains($methods, $marker)) {
                continue;
            }

            $uses[] = 'use ' . self::CONTRACTS . $class . ';';
        }

        return implode("\n", [...$uses, 'use InvalidArgumentException;', 'use Override;']);
    }
}
