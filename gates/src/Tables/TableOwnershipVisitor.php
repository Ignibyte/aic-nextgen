<?php

declare(strict_types=1);

namespace Gates\Tables;

use AIC\Entity\Domain\EntityType;
use Gates\Domain\TableName;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

use function in_array;

/**
 * gate:26's tables in one module file (#185): each table a migration creates
 * or views, each one it alters, and each one an entity type names, with
 * the line; a table not written as a literal is named empty, which the rule
 * refuses.
 */
final class TableOwnershipVisitor extends NodeVisitorAbstract
{
    /** A migration's calls that make its module a table's owner. */
    private const array OWNING = ['create', 'view'];

    /**
     * Tables the file creates or views.
     *
     * @var list<TableName>
     */
    private array $owned = [];

    /**
     * Tables the file alters or names in an entity type.
     *
     * @var list<TableName>
     */
    private array $named = [];

    /**
     * @param bool $migration Whether the file is a migration, whose `create`, `view` and `alter` calls count.
     */
    public function __construct(
        private readonly bool $migration,
    ) {
    }

    /**
     * @param Node $node A node of the file.
     */
    #[Override]
    public function enterNode(Node $node): null
    {
        if ($node instanceof New_ && $node->class instanceof Name && $node->class->toString() === EntityType::class) {
            $this->named[] = self::table($node->getArgs()[0]->value ?? null, $node->getStartLine(), 'an entity type');
        }

        if ($this->migration && $node instanceof MethodCall && $node->name instanceof Identifier) {
            $this->call($node, $node->name->toString());
        }

        return null;
    }

    /**
     * @return list<TableName> The tables the file creates or views.
     */
    public function owned(): array
    {
        return $this->owned;
    }

    /**
     * @return list<TableName> The tables the file alters or names in an entity type.
     */
    public function named(): array
    {
        return $this->named;
    }

    /**
     * @param MethodCall $methodCall A method call in a migration.
     * @param string     $method     Its name.
     */
    private function call(MethodCall $methodCall, string $method): void
    {
        $first = $methodCall->getArgs()[0]->value ?? null;

        if (in_array($method, self::OWNING, true)) {
            $this->owned[] = self::table($first, $methodCall->getStartLine(), 'a migration');
        }

        if ($method !== 'alter') {
            return;
        }

        $this->named[] = self::table($first, $methodCall->getStartLine(), 'an alteration');
    }

    /**
     * @param Node|null $node What names the table.
     * @param int       $line Its line.
     * @param string    $what What names it, for the message.
     *
     * @return TableName The table, empty when it is not a literal.
     */
    private static function table(?Node $node, int $line, string $what): TableName
    {
        return new TableName($node instanceof String_ ? $node->value : '', $line, $what);
    }
}
