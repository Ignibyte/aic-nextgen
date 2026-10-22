<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;

use function array_keys;
use function array_map;
use function implode;
use function strrpos;
use function substr;

/**
 * Lowers a template's loops (#97): `foreach` and `for` to real loops whose
 * variables are typed parameters of the parts their bodies compile to, and
 * `break` and `continue`, each alone and inside a loop.
 */
final readonly class TemplateLoopLowering
{
    /**
     * @param TemplateLowering  $templateLowering  Lowers the bodies and prints the expressions.
     * @param TemplateTypes     $templateTypes     Types the conditions and what a loop runs over.
     * @param TemplateLoopTypes $templateLoopTypes A loop's variables.
     */
    public function __construct(
        private TemplateLowering $templateLowering,
        private TemplateTypes $templateTypes = new TemplateTypes(),
        private TemplateLoopTypes $templateLoopTypes = new TemplateLoopTypes(),
    ) {
    }

    /**
     * @param Foreach_|For_|Break_|Continue_ $stmt  A loop, or a jump out of one.
     * @param array<string, TemplateType>    $scope The variables in scope.
     * @param int                            $loops How many loops enclose it.
     *
     * @throws InvalidArgumentException When a loop's types are refused, or a jump is outside a loop or names a level.
     *
     * @return TemplateNode Its node.
     */
    public function lower(Foreach_|For_|Break_|Continue_ $stmt, array $scope, int $loops): TemplateNode
    {
        return match (true) {
            $stmt instanceof Foreach_ => $this->foreach($stmt, $scope, $loops),
            $stmt instanceof For_ => $this->for($stmt, $scope, $loops),
            default => $this->control($stmt, $loops),
        };
    }

    /**
     * @param Foreach_                    $foreach A `foreach`.
     * @param array<string, TemplateType> $scope   The variables in scope.
     * @param int                         $loops   How many loops enclose it.
     *
     * @throws InvalidArgumentException When it does not loop over a typed list.
     *
     * @return TemplateNode The loop.
     */
    private function foreach(Foreach_ $foreach, array $scope, int $loops): TemplateNode
    {
        $templateType = $this->templateTypes->type($foreach->expr, $scope);
        [$bound, $parameters] = $this->templateLoopTypes->foreach($foreach, $templateType, $this->templateLowering);
        $header = $this->templateLowering->code($foreach->expr, $scope) . ' as ' . implode(' => ', array_map(
            static fn (string $parameter): string => substr($parameter, (int) strrpos($parameter, ' ') + 1),
            $parameters,
        ));
        $children = $this->templateLowering->statements($foreach->stmts, [...$scope, ...$bound], $loops + 1);

        return new TemplateNode(TemplateNodeKind::Loop, $header, $children, scope: $parameters);
    }

    /**
     * @param For_                        $for   A `for`.
     * @param array<string, TemplateType> $scope The variables in scope.
     * @param int                         $loops How many loops enclose it.
     *
     * @throws InvalidArgumentException When its counter is not an int or its condition not a bool.
     *
     * @return TemplateNode The loop.
     */
    private function for(For_ $for, array $scope, int $loops): TemplateNode
    {
        $bound = $this->templateLoopTypes->counters($for);
        $inner = [...$scope, ...$bound];

        foreach ($for->cond as $cond) {
            $this->templateTypes->condition($cond, $inner);
        }

        $header = implode('; ', [
            implode(', ', array_map(fn (Expr $expr): string => $this->templateLowering->code($expr, $inner), $for->init)),
            implode(', ', array_map(fn (Expr $expr): string => $this->templateLowering->code($expr, $inner), $for->cond)),
            implode(', ', array_map(fn (Expr $expr): string => $this->templateLowering->code($expr, $inner), $for->loop)),
        ]);
        $parameters = array_map(static fn (string $name): string => 'int $' . $name, array_keys($bound));

        $children = $this->templateLowering->statements($for->stmts, $inner, $loops + 1);

        return new TemplateNode(TemplateNodeKind::Counter, $header, $children, scope: $parameters);
    }

    /**
     * @param Break_|Continue_ $stmt  `break` or `continue`.
     * @param int              $loops How many loops enclose it.
     *
     * @throws InvalidArgumentException When it is outside a loop, or names a level.
     *
     * @return TemplateNode Its node.
     */
    private function control(Break_|Continue_ $stmt, int $loops): TemplateNode
    {
        if ($loops === 0 || $stmt->num !== null) {
            throw new InvalidArgumentException(
                TemplateRefusal::NotCompilable->value . ': break and continue stand alone, inside a loop',
            );
        }

        return new TemplateNode($stmt instanceof Break_ ? TemplateNodeKind::Break : TemplateNodeKind::Continue, '');
    }
}
