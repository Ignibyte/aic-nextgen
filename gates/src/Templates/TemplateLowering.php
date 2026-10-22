<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\InlineHTML;

use function array_key_exists;
use function preg_match;
use function strrpos;
use function substr;

/**
 * Lowers a validated template's statements to the compiler's nodes (#97):
 * markup as text, each `<?= ?>` by the escaping rule, `if`/`elseif`/`else`
 * and `switch` to conditions, `foreach` and `for` to real loops whose
 * variables are typed, `break` and `continue` as they are. Every expression is
 * typed and printed; each class it names becomes an import. A refusal carries
 * the line of the statement it came from.
 */
final class TemplateLowering
{
    /**
     * The components rendered so far, for the next one's `Name.N` key.
     */
    private int $components = 0;

    /**
     * @var array<string, string> The classes the generated code names, fully qualified, by short name.
     */
    private array $imports = [];

    /**
     * @param string         $name           The component's short class name, for its keys.
     * @param TemplateTypes  $templateTypes  Types expressions.
     * @param TemplateNames  $templateNames  Prints expressions.
     * @param TemplateEchoes $templateEchoes The escaping rule.
     */
    public function __construct(
        private readonly string $name,
        private readonly TemplateTypes $templateTypes = new TemplateTypes(),
        private readonly TemplateNames $templateNames = new TemplateNames(),
        private readonly TemplateEchoes $templateEchoes = new TemplateEchoes(),
    ) {
    }

    /**
     * @param list<Stmt>                  $stmts The template's statements, the typing line marked.
     * @param array<string, TemplateType> $scope The component variable and its class.
     *
     * @throws InvalidArgumentException When a statement is refused: `line: category: detail`.
     *
     * @return list<TemplateNode> The nodes, then one import per class the generated code names.
     */
    public function lower(array $stmts, array $scope): array
    {
        $nodes = $this->statements($stmts, $scope, 0);

        foreach ($this->imports as $import) {
            $nodes[] = new TemplateNode(TemplateNodeKind::Import, $import);
        }

        return $nodes;
    }

    /**
     * @param array<array-key, Stmt>      $stmts A statement list.
     * @param array<string, TemplateType> $scope The variables in scope.
     * @param int                         $loops How many loops enclose it.
     *
     * @throws InvalidArgumentException When a statement is refused.
     *
     * @return list<TemplateNode> Its nodes.
     */
    public function statements(array $stmts, array $scope, int $loops): array
    {
        $nodes = [];

        foreach ($stmts as $stmt) {
            try {
                $nodes = [...$nodes, ...$this->statement($stmt, $scope, $loops)];
            } catch (InvalidArgumentException $invalidArgumentException) {
                throw $this->located($invalidArgumentException, $stmt->getStartLine());
            }
        }

        return $nodes;
    }

    /**
     * @param Expr                        $expr  An expression.
     * @param array<string, TemplateType> $scope The variables in scope.
     *
     * @throws InvalidArgumentException When it cannot be printed: a class name not fully qualified, or two of one short name.
     *
     * @return string Its code for the generated class; its classes are imported.
     */
    public function code(Expr $expr, array $scope): string
    {
        [$code, $imports] = $this->templateNames->print($expr, $scope);

        foreach ($imports as $import) {
            $this->import($import);
        }

        return $code;
    }

    /**
     * @param string $class A class the generated code names, fully qualified.
     *
     * @throws InvalidArgumentException When another class of the same short name is imported already.
     *
     * @return string Its short name.
     */
    public function import(string $class): string
    {
        $short = substr($class, (int) strrpos($class, '\\') + 1);

        if (array_key_exists($short, $this->imports) && $this->imports[$short] !== $class) {
            throw new InvalidArgumentException(
                TemplateRefusal::ClassName->value . ': ' . $class . ' and ' . $this->imports[$short] . ' share a short name',
            );
        }

        $this->imports[$short] = $class;

        return $short;
    }

    /**
     * @param Stmt                        $stmt  A statement the grammar allows.
     * @param array<string, TemplateType> $scope The variables in scope.
     * @param int                         $loops How many loops enclose it.
     *
     * @throws InvalidArgumentException When it is refused.
     *
     * @return list<TemplateNode> Its nodes.
     */
    private function statement(Stmt $stmt, array $scope, int $loops): array
    {
        return match (true) {
            $stmt instanceof InlineHTML => [new TemplateNode(TemplateNodeKind::Text, $stmt->value)],
            $stmt instanceof Echo_ => [$this->echo($stmt->exprs[0], $scope, $loops > 0)],
            default => (new TemplateFlow($this))->lower($stmt, $scope, $loops),
        };
    }

    /**
     * @param Expr                        $expr   The expression `<?= ?>` writes.
     * @param array<string, TemplateType> $scope  The variables in scope.
     * @param bool                        $inLoop Whether it is inside a loop.
     *
     * @throws InvalidArgumentException When its type is not one `<?= ?>` writes.
     *
     * @return TemplateNode Its node.
     */
    private function echo(Expr $expr, array $scope, bool $inLoop): TemplateNode
    {
        $type = $this->templateTypes->type($expr, $scope);
        $marker = $this->name . '.' . ($this->components + 1);
        $templateNode = $this->templateEchoes->echo($this->code($expr, $scope), $type, $marker, $inLoop);

        if ($templateNode->kind === TemplateNodeKind::Component) {
            $this->components += 1;
        }

        return $templateNode;
    }

    /**
     * @param InvalidArgumentException $invalidArgumentException A refusal.
     * @param int                      $line                     The line of the statement it came from.
     *
     * @return InvalidArgumentException It with its line in front, unless an inner statement put one there.
     */
    private function located(InvalidArgumentException $invalidArgumentException, int $line): InvalidArgumentException
    {
        return preg_match('/^\\d+: /', $invalidArgumentException->getMessage()) === 1
            ? $invalidArgumentException
            : new InvalidArgumentException($line . ': ' . $invalidArgumentException->getMessage(), 0, $invalidArgumentException);
    }
}
