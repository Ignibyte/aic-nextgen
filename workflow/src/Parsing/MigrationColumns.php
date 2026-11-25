<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Domain\Make\MigratedColumn;
use AIC\Workflow\Domain\Make\MigratedTable;
use AIC\Workflow\Exception\WorkflowRefusal;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

use function array_key_exists;
use function array_pop;
use function array_slice;
use function array_unshift;
use function in_array;
use function spl_object_id;

/**
 * The columns a migration declares for one table (#338), read from its source,
 * never run: every method chain on the table's builder, `$schema->create('t')`
 * or a `$tableBuilder` handed to a helper, as the importer and make:entity
 * write them, in the order they stand. Its builder's type methods, `column()`
 * with a `ColumnTypes` call (through `defaulted()` and `generated()`),
 * `nullable()`, `primary()` and `foreignKey()` are read; everything else on
 * the builder (indexes, checks) is passed over.
 */
final readonly class MigrationColumns
{
    /**
     * The builder's own column methods, each taking the column's name first.
     */
    private const array BUILDER = ['identity', 'string', 'text', 'integer', 'bigInteger', 'timestampTz', 'date', 'numeric', 'boolean'];

    /**
     * The `ColumnTypes` calls a `column()` may name.
     */
    private const array TYPES = [...self::BUILDER, 'uuid', 'uuidKey', 'jsonb', 'choice'];

    /**
     * @param ManifestSyntax $manifestSyntax Parses the source.
     * @param NodeFinder     $nodeFinder     Finds the calls.
     * @param CallReader     $callReader     Reads a call's method and arguments.
     */
    public function __construct(
        private ManifestSyntax $manifestSyntax = new ManifestSyntax(),
        private NodeFinder $nodeFinder = new NodeFinder(),
        private CallReader $callReader = new CallReader(),
    ) {
    }

    /**
     * @param string $source The migration's source.
     * @param string $file   Its path, relative to the project.
     * @param string $table  The table it creates.
     *
     * @throws WorkflowRefusal When it does not parse, declares a column of a type this reads no field of, or no column.
     *
     * @return MigratedTable The table as it declares it.
     */
    public function table(string $source, string $file, string $table): MigratedTable
    {
        $read = [[], [], []];

        foreach ($this->chains($this->manifestSyntax->parse($source, $file), $table) as $chain) {
            foreach ($chain as $methodCall) {
                $read = $this->step($methodCall, ...$read);
            }
        }

        [$columns, $primary, $references] = $read;

        if ($columns === []) {
            throw new WorkflowRefusal($file . ' declares no column of ' . $table . ' on a builder chain make:entity reads.');
        }

        return new MigratedTable($table, $this->referenced($columns, $references), $primary, $file);
    }

    /**
     * @param list<Stmt> $stmts The migration's statements.
     * @param string     $table The table.
     *
     * @return list<list<MethodCall>> Each chain on the table's builder, its calls from the first, in source order.
     */
    private function chains(array $stmts, string $table): array
    {
        $calls = $this->nodeFinder->findInstanceOf($stmts, MethodCall::class);
        $inner = $this->inner($calls);
        $chains = [];

        foreach ($calls as $call) {
            if (array_key_exists(spl_object_id($call), $inner)) {
                continue;
            }

            $chain = $this->onTable($call, $table);

            if ($chain === []) {
                continue;
            }

            $chains[] = $chain;
        }

        return $chains;
    }

    /**
     * @param MethodCall            $methodCall A chain's last call.
     * @param list<MigratedColumn>  $columns    The columns so far.
     * @param list<string>          $primary    The primary key so far.
     * @param array<string, string> $references Each foreign key's table, by column.
     *
     * @throws WorkflowRefusal When a column's type is one this reads no field of.
     *
     * @return array{list<MigratedColumn>, list<string>, array<string, string>} The three, with the call read.
     */
    private function step(MethodCall $methodCall, array $columns, array $primary, array $references): array
    {
        $name = $this->callReader->called($methodCall);
        $column = $this->column($methodCall, $name);

        if ($column instanceof MigratedColumn) {
            return [[...$columns, $column], $primary, $references];
        }

        return match (true) {
            $name === 'nullable' => [$this->lastNullable($columns), $primary, $references],
            $name === 'primary' => [$columns, $this->texts($this->callReader->argument($methodCall, 0)), $references],
            $name === 'foreignKey' => [$columns, $primary, [...$references, ...$this->foreignKey($methodCall)]],
            default => [$columns, $primary, $references],
        };
    }

    /**
     * @param MethodCall $methodCall A `foreignKey()` call.
     *
     * @return array<string, string> Its column and the table it references.
     */
    private function foreignKey(MethodCall $methodCall): array
    {
        return [$this->callReader->text($methodCall, 0) => $this->callReader->text($methodCall, 1)];
    }

    /**
     * @param MethodCall $methodCall A call on the builder.
     * @param string     $name       Its method.
     *
     * @throws WorkflowRefusal When it is `column()` with a type this reads no field of.
     *
     * @return MigratedColumn|null The column it declares; null when it declares none.
     */
    private function column(MethodCall $methodCall, string $name): ?MigratedColumn
    {
        if (in_array($name, self::BUILDER, true)) {
            return new MigratedColumn($this->callReader->text($methodCall, 0), $name);
        }

        return $name === 'column'
            ? $this->typed($this->callReader->text($methodCall, 0), $this->callReader->argument($methodCall, 1))
            : null;
    }

    /**
     * @param string    $name The column's name.
     * @param Expr|null $expr What `column()` was handed.
     *
     * @throws WorkflowRefusal When it is not a `ColumnTypes` call this reads.
     *
     * @return MigratedColumn The column.
     */
    private function typed(string $name, ?Expr $expr): MigratedColumn
    {
        [$inner, $generated] = $this->unwrapped($expr);
        $method = $inner instanceof MethodCall
            ? $this->callReader->called($inner)
            : '';

        if (!$inner instanceof MethodCall || !in_array($method, self::TYPES, true)) {
            throw new WorkflowRefusal('the column ' . $name . ' has a type make:entity reads no field of.');
        }

        $choices = $method === 'choice'
            ? $this->texts($this->callReader->argument($inner, 0))
            : [];

        return new MigratedColumn($name, $method, false, $choices, '', $generated);
    }

    /**
     * @param Expr|null $expr An argument.
     *
     * @return list<string> Its string literals when it is an array of them.
     */
    private function texts(?Expr $expr): array
    {
        if (!$expr instanceof Array_) {
            return [];
        }

        $texts = [];

        foreach ($this->nodeFinder->findInstanceOf($expr, String_::class) as $string) {
            $texts[] = $string->value;
        }

        return $texts;
    }

    /**
     * @param Expr|null $expr What `column()` was handed.
     *
     * @return array{Expr|null, bool} The type inside any `defaulted()` and `generated()`, and whether it is generated.
     */
    private function unwrapped(?Expr $expr): array
    {
        $generated = false;

        while (
            $expr instanceof MethodCall
            && in_array($this->callReader->called($expr), ['defaulted', 'generated'], true)
        ) {
            $generated = $generated || $this->callReader->called($expr) === 'generated';
            $expr = $this->callReader->argument($expr, 0);
        }

        return [$expr, $generated];
    }

    /**
     * @param MethodCall $methodCall A chain's last call.
     * @param string     $table      The table.
     *
     * @return list<MethodCall> Its calls after the builder, from the first, when it is on the table's builder; empty otherwise.
     */
    private function onTable(MethodCall $methodCall, string $table): array
    {
        [$root, $calls] = $this->unwound($methodCall);

        if ($root instanceof Variable && $root->name === 'tableBuilder') {
            return $calls;
        }

        $creates = $root instanceof Variable && $root->name === 'schema' && $this->callReader->called($calls[0]) === 'create'
            && $this->callReader->text($calls[0], 0) === $table;

        return $creates
            ? array_slice($calls, 1)
            : [];
    }

    /**
     * @param MethodCall $methodCall A chain's last call.
     *
     * @return array{Expr, non-empty-list<MethodCall>} What the chain starts on, and its calls from the first.
     */
    private function unwound(MethodCall $methodCall): array
    {
        $calls = [$methodCall];
        $node = $methodCall->var;

        while ($node instanceof MethodCall) {
            array_unshift($calls, $node);
            $node = $node->var;
        }

        return [$node, $calls];
    }

    /**
     * @param array<MethodCall> $calls Every call.
     *
     * @return array<int, true> The calls another call is made on, by object id.
     */
    private function inner(array $calls): array
    {
        $inner = [];

        foreach ($calls as $call) {
            $inner[spl_object_id($call->var)] = true;
        }

        return $inner;
    }

    /**
     * @param list<MigratedColumn> $columns The columns.
     *
     * @return list<MigratedColumn> Them, the last nullable; none when there are none.
     */
    private function lastNullable(array $columns): array
    {
        $last = array_pop($columns);

        return $last instanceof MigratedColumn
            ? [...$columns, $last->asNullable()]
            : $columns;
    }

    /**
     * @param list<MigratedColumn>  $columns    The columns.
     * @param array<string, string> $references Each foreign key's table, by column.
     *
     * @return list<MigratedColumn> The columns, each foreign key's with its table.
     */
    private function referenced(array $columns, array $references): array
    {
        $referenced = [];

        foreach ($columns as $column) {
            $referenced[] = array_key_exists($column->name, $references)
                ? $column->referencing($references[$column->name])
                : $column;
        }

        return $referenced;
    }
}
