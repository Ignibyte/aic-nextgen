<?php

declare(strict_types=1);

namespace Gates\Tables;

use Gates\Domain\TableName;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitorAbstract;

use function in_array;
use function preg_match_all;

/**
 * gate:26's view of one module migration for the version order (#228): its
 * version, the tables it creates or views, and the tables it names in a
 * foreign key or in SQL (`FROM`, `JOIN` or `REFERENCES` in any string
 * literal of the file), each with its line.
 */
final class MigrationOrderVisitor extends NodeVisitorAbstract
{
    /** A table named in SQL; a name followed by `(` is a function, not a table. */
    private const string SQL_TABLE = '/\\b(?:FROM|JOIN|REFERENCES)\\s+\\K[a-z_][a-z0-9_]*\\b(?!\\s*\\()/i';

    /** The calls that create a table or a view. */
    private const array CREATING = ['create', 'view'];

    /** The migration's version, from `version()`'s literal return. */
    private string $version = '';

    /**
     * The tables the migration creates or views.
     *
     * @var list<string>
     */
    private array $created = [];

    /**
     * The tables the migration names in a foreign key or in SQL.
     *
     * @var list<TableName>
     */
    private array $referenced = [];

    /**
     * @param Node $node A node of the migration.
     */
    #[Override]
    public function enterNode(Node $node): null
    {
        if ($node instanceof ClassMethod && $node->name->toString() === 'version') {
            $this->version = self::returned($node);
        }

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $this->call($node, $node->name->toString());
        }

        if ($node instanceof String_) {
            $this->sql($node);
        }

        return null;
    }

    /**
     * @return string The version, empty when it is not a literal.
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return list<string> The tables it creates or views.
     */
    public function created(): array
    {
        return $this->created;
    }

    /**
     * @return list<TableName> The tables it names.
     */
    public function referenced(): array
    {
        return $this->referenced;
    }

    /**
     * @param MethodCall $methodCall A call in the migration.
     * @param string     $method     Its name.
     */
    private function call(MethodCall $methodCall, string $method): void
    {
        $args = $methodCall->getArgs();
        $first = $args[0]->value ?? null;

        if (in_array($method, self::CREATING, true) && $first instanceof String_) {
            $this->created[] = $first->value;
        }

        $second = $args[1]->value ?? null;

        if ($method !== 'foreignKey' || !($second instanceof String_)) {
            return;
        }

        $this->referenced[] = new TableName($second->value, $methodCall->getStartLine(), 'a foreign key');
    }

    /**
     * @param String_ $string A string literal of the migration.
     */
    private function sql(String_ $string): void
    {
        preg_match_all(self::SQL_TABLE, $string->value, $matches);

        // The pattern has no capture group, so the one group is the whole matches: each a table's name.
        foreach ($matches as $match) {
            foreach ($match as $table) {
                $this->referenced[] = new TableName($table, $string->getStartLine(), 'its SQL');
            }
        }
    }

    /**
     * @param ClassMethod $classMethod The `version()` method.
     *
     * @return string Its literal return, empty when it returns anything else.
     */
    private static function returned(ClassMethod $classMethod): string
    {
        foreach ($classMethod->stmts ?? [] as $statement) {
            if ($statement instanceof Return_ && $statement->expr instanceof String_) {
                return $statement->expr->value;
            }
        }

        return '';
    }
}
