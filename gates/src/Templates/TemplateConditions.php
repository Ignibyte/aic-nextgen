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
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;

use function array_map;
use function array_pop;
use function array_reverse;
use function array_values;
use function count;
use function implode;

/**
 * Lowers a template's conditions (#97): `if`/`elseif`/`else` to one condition
 * whose `elseif`s nest inside its block markers, and `switch` to the same
 * chain, testing each case with `===` against a subject of the same type.
 * Every condition is a bool; each non-empty case ends in `break`, and empty
 * cases join the next.
 */
final readonly class TemplateConditions
{
    /**
     * @param TemplateLowering $templateLowering Lowers the branches and prints the conditions.
     * @param TemplateTypes    $templateTypes    Types the conditions and cases.
     */
    public function __construct(
        private TemplateLowering $templateLowering,
        private TemplateTypes $templateTypes = new TemplateTypes(),
    ) {
    }

    /**
     * @param If_|Switch_                 $stmt  An `if` or a `switch` in the alternative syntax.
     * @param array<string, TemplateType> $scope The variables in scope.
     * @param int                         $loops How many loops enclose it.
     *
     * @throws InvalidArgumentException When a condition is not a bool, a case's type differs from the subject's, or a branch is refused.
     *
     * @return TemplateNode The condition, its `elseif`s nested in its otherwise.
     */
    public function lower(If_|Switch_ $stmt, array $scope, int $loops): TemplateNode
    {
        if ($stmt instanceof Switch_) {
            return $this->switch($stmt, $scope, $loops);
        }

        $otherwise = $stmt->else === null
            ? []
            : $this->templateLowering->statements($stmt->else->stmts, $scope, $loops);

        foreach (array_reverse($stmt->elseifs) as $elseIf) {
            $otherwise = [$this->branch(TemplateNodeKind::ElseIf, $elseIf->cond, $elseIf->stmts, $otherwise, $scope, $loops)];
        }

        return $this->branch(TemplateNodeKind::Condition, $stmt->cond, $stmt->stmts, $otherwise, $scope, $loops);
    }

    /**
     * @param Switch_                     $switch A `switch` in the alternative syntax.
     * @param array<string, TemplateType> $scope  The variables in scope.
     * @param int                         $loops  How many loops enclose it.
     *
     * @throws InvalidArgumentException When a case's type differs from the subject's, or a case does not end in `break`.
     *
     * @return TemplateNode The chain of conditions, one per group of cases, the default last.
     */
    private function switch(Switch_ $switch, array $scope, int $loops): TemplateNode
    {
        $templateType = $this->templateTypes->type($switch->cond, $scope);
        $code = $this->templateLowering->code($switch->cond, $scope);
        [$groups, $default] = $this->groups($switch, $templateType, $scope);
        $otherwise = $this->templateLowering->statements($default, $scope, $loops);
        $node = null;

        foreach (array_reverse($groups) as $index => [$values, $stmts]) {
            $tests = array_map(fn (Expr $expr): string => $code . ' === ' . $this->templateLowering->code($expr, $scope), $values);
            $kind = $index === count($groups) - 1
                ? TemplateNodeKind::Condition
                : TemplateNodeKind::ElseIf;
            $children = $this->templateLowering->statements($stmts, $scope, $loops);
            $node = new TemplateNode($kind, implode(' || ', $tests), $children, $otherwise);
            $otherwise = [$node];
        }

        return $node ?? throw new InvalidArgumentException(TemplateRefusal::NotCompilable->value . ': a switch has at least one case');
    }

    /**
     * @param TemplateNodeKind            $templateNodeKind `Condition`, or `ElseIf` inside a chain.
     * @param Expr                        $expr             The condition.
     * @param array<array-key, Stmt>      $stmts            The branch.
     * @param list<TemplateNode>          $otherwise        What follows when it does not hold.
     * @param array<string, TemplateType> $scope            The variables in scope.
     * @param int                         $loops            How many loops enclose it.
     *
     * @throws InvalidArgumentException When the condition is not a bool, or the branch is refused.
     *
     * @return TemplateNode The condition.
     */
    private function branch(
        TemplateNodeKind $templateNodeKind,
        Expr $expr,
        array $stmts,
        array $otherwise,
        array $scope,
        int $loops,
    ): TemplateNode {
        $this->templateTypes->condition($expr, $scope);

        return new TemplateNode(
            $templateNodeKind,
            $this->templateLowering->code($expr, $scope),
            $this->templateLowering->statements($stmts, $scope, $loops),
            $otherwise,
        );
    }

    /**
     * @param Switch_                     $switch       A `switch`.
     * @param TemplateType                $templateType Its subject's type.
     * @param array<string, TemplateType> $scope        The variables in scope.
     *
     * @throws InvalidArgumentException When a case's type differs from the subject's, or a case does not end in `break`.
     *
     * @return array{list<array{list<Expr>, list<Stmt>}>, list<Stmt>} The case groups, each its values and its statements
     *                                                                without the closing `break`; the default's statements.
     */
    private function groups(Switch_ $switch, TemplateType $templateType, array $scope): array
    {
        $groups = [];
        $pending = [];
        $default = [];

        foreach ($switch->cases as $case) {
            $condition = $case->cond;

            if (!$condition instanceof Expr) {
                $default = $this->closed($case->stmts);

                continue;
            }

            $this->sameType($condition, $templateType, $scope);
            $pending[] = $condition;

            if ($case->stmts === []) {
                continue;
            }

            $groups[] = [$pending, $this->closed($case->stmts)];
            $pending = [];
        }

        return [$groups, $default];
    }

    /**
     * @param Expr                        $expr         A case's value.
     * @param TemplateType                $templateType The switch subject's type.
     * @param array<string, TemplateType> $scope        The variables in scope.
     *
     * @throws InvalidArgumentException When their types differ.
     */
    private function sameType(Expr $expr, TemplateType $templateType, array $scope): void
    {
        if ($this->templateTypes->type($expr, $scope)->kinds !== $templateType->kinds) {
            throw new InvalidArgumentException(
                TemplateRefusal::ConditionType->value . ": a case has the switch subject's type, so === and == agree",
            );
        }
    }

    /**
     * @param array<array-key, Stmt> $stmts A case's statements.
     *
     * @throws InvalidArgumentException When they do not end in `break`, or hold another.
     *
     * @return list<Stmt> Them without the closing `break`.
     */
    private function closed(array $stmts): array
    {
        $kept = array_values($stmts);
        $last = array_pop($kept);

        if (!$last instanceof Break_ || (new TemplateBreaks())->holds($kept)) {
            throw new InvalidArgumentException(TemplateRefusal::NotCompilable->value . ': each case ends in one break, and holds no other');
        }

        return $kept;
    }
}
