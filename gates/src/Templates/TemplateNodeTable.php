<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;

use function array_key_exists;
use function in_array;
use function str_starts_with;

/**
 * The template grammar as a table over php-parser's node types (#96), keyed by
 * `Node::getType()`. A type is allowed, allowed once a rule checks it, or
 * refused with a category. Deny by default: a type the table does not name is
 * `not in the grammar`, so a node a later PHP adds never slips through.
 */
final readonly class TemplateNodeTable
{
    /**
     * Allowed as they are.
     */
    private const array ALLOWED = [
        'Arg',
        'Expr_Array',
        'Expr_BinaryOp_BooleanAnd',
        'Expr_BinaryOp_BooleanOr',
        'Expr_BinaryOp_Coalesce',
        'Expr_BinaryOp_Concat',
        'Expr_BinaryOp_Div',
        'Expr_BinaryOp_Equal',
        'Expr_BinaryOp_Greater',
        'Expr_BinaryOp_GreaterOrEqual',
        'Expr_BinaryOp_Identical',
        'Expr_BinaryOp_Minus',
        'Expr_BinaryOp_Mod',
        'Expr_BinaryOp_Mul',
        'Expr_BinaryOp_NotEqual',
        'Expr_BinaryOp_NotIdentical',
        'Expr_BinaryOp_Plus',
        'Expr_BinaryOp_Pow',
        'Expr_BinaryOp_Smaller',
        'Expr_BinaryOp_SmallerOrEqual',
        'Expr_BinaryOp_Spaceship',
        'Expr_BooleanNot',
        'Expr_Empty',
        'Expr_Isset',
        'Expr_Match',
        'Expr_Ternary',
        'Expr_UnaryMinus',
        'Expr_UnaryPlus',
        'Identifier',
        'InterpolatedStringPart',
        'MatchArm',
        'Name',
        'Name_FullyQualified',
        'Scalar_Float',
        'Scalar_Int',
        'Scalar_InterpolatedString',
        'Scalar_String',
        'Stmt_Break',
        'Stmt_Case',
        'Stmt_Continue',
        'Stmt_Else',
        'Stmt_ElseIf',
        'Stmt_InlineHTML',
        'VarLikeIdentifier',
    ];

    /**
     * Allowed once a rule checks them (`TemplateStatementRules`, `TemplateExpressionRules`).
     */
    private const array CHECKED = [
        'ArrayItem',
        'Expr_ArrayDimFetch',
        'Expr_Assign',
        'Expr_ClassConstFetch',
        'Expr_ConstFetch',
        'Expr_FuncCall',
        'Expr_NullsafePropertyFetch',
        'Expr_PostDec',
        'Expr_PostInc',
        'Expr_PreDec',
        'Expr_PreInc',
        'Expr_PropertyFetch',
        'Expr_Variable',
        'Stmt_Echo',
        'Stmt_Expression',
        'Stmt_For',
        'Stmt_Foreach',
        'Stmt_If',
        'Stmt_Nop',
        'Stmt_Switch',
    ];

    /**
     * Refused, with their category.
     */
    private const array REFUSED = [
        'Expr_ArrowFunction' => TemplateRefusal::Closure,
        'Expr_AssignRef' => TemplateRefusal::Assignment,
        'Expr_Closure' => TemplateRefusal::Closure,
        'Expr_ErrorSuppress' => TemplateRefusal::Suppress,
        'Expr_Eval' => TemplateRefusal::EvalCall,
        'Expr_Exit' => TemplateRefusal::ExitCall,
        'Expr_Include' => TemplateRefusal::IncludeRequire,
        'Expr_Instanceof' => TemplateRefusal::InstanceofTest,
        'Expr_MethodCall' => TemplateRefusal::MethodCall,
        'Expr_New' => TemplateRefusal::NewObject,
        'Expr_NullsafeMethodCall' => TemplateRefusal::MethodCall,
        'Expr_Print' => TemplateRefusal::EchoPrint,
        'Expr_ShellExec' => TemplateRefusal::Backtick,
        'Expr_StaticCall' => TemplateRefusal::StaticCall,
        'Stmt_Block' => TemplateRefusal::PhpBlock,
        'Stmt_Class' => TemplateRefusal::Declaration,
        'Stmt_Const' => TemplateRefusal::Declaration,
        'Stmt_Declare' => TemplateRefusal::Declaration,
        'Stmt_Enum' => TemplateRefusal::Declaration,
        'Stmt_Function' => TemplateRefusal::Declaration,
        'Stmt_Global' => TemplateRefusal::GlobalVariable,
        'Stmt_Goto' => TemplateRefusal::PhpBlock,
        'Stmt_GroupUse' => TemplateRefusal::Declaration,
        'Stmt_HaltCompiler' => TemplateRefusal::PhpBlock,
        'Stmt_Interface' => TemplateRefusal::Declaration,
        'Stmt_Label' => TemplateRefusal::PhpBlock,
        'Stmt_Namespace' => TemplateRefusal::Declaration,
        'Stmt_Return' => TemplateRefusal::PhpBlock,
        'Stmt_Static' => TemplateRefusal::StaticVariable,
        'Stmt_Trait' => TemplateRefusal::Declaration,
        'Stmt_TryCatch' => TemplateRefusal::PhpBlock,
        'Stmt_Unset' => TemplateRefusal::PhpBlock,
        'Stmt_Use' => TemplateRefusal::Declaration,
    ];

    /**
     * What each category refuses, and the fix, for the line gate:25 prints.
     */
    private const array DETAILS = [
        '@' => 'a template suppresses no error',
        'assignment' => "a template assigns nothing, but a for loop's own counter",
        'backtick' => 'a template runs no command',
        'closure' => 'a template declares no closure',
        'declaration' => 'a template declares nothing',
        'echo/print' => 'write <?= expression ?>',
        'eval' => 'a template evaluates no code',
        'exit' => 'a template never exits',
        'global' => 'a template reads only its component and its loop variables',
        'include/require' => 'a template includes nothing; render a child component',
        'instanceof' => 'a template tests no type; the component exposes the answer',
        'method call' => 'a template calls no method; read a property the component exposes',
        'new' => 'a template builds nothing; the component passes it in',
        'php block' => 'a <?php ?> block holds only control flow in the alternative syntax',
        'static' => 'a template keeps no state',
        'static call' => 'a template calls no static method',
    ];

    /**
     * @param string $type A node's type (`Expr_MethodCall`).
     *
     * @return bool Whether it is allowed with no check.
     */
    public function allowed(string $type): bool
    {
        return in_array($type, self::ALLOWED, true);
    }

    /**
     * @param string $type A node's type.
     *
     * @return bool Whether a rule checks it: the checked types, and every compound assignment (`Expr_AssignOp_*`).
     */
    public function checked(string $type): bool
    {
        return in_array($type, self::CHECKED, true) || str_starts_with($type, 'Expr_AssignOp_');
    }

    /**
     * @param string $type A node's type that is neither allowed nor checked.
     *
     * @return array{TemplateRefusal, string} Its category and detail; `not in the grammar` when the table does not name it.
     */
    public function refusal(string $type): array
    {
        if (!array_key_exists($type, self::REFUSED)) {
            return [TemplateRefusal::NotInGrammar, $this->unknown($type)];
        }

        $templateRefusal = self::REFUSED[$type];

        return [$templateRefusal, self::DETAILS[$templateRefusal->value]];
    }

    /**
     * @param TemplateRefusal $templateRefusal A category.
     *
     * @return string What it refuses, and the fix.
     */
    public function detail(TemplateRefusal $templateRefusal): string
    {
        return self::DETAILS[$templateRefusal->value] ?? $templateRefusal->value;
    }

    /**
     * @param string $type A node type the table does not name.
     *
     * @return string Its detail.
     */
    private function unknown(string $type): string
    {
        return match ($type) {
            'Stmt_While', 'Stmt_Do' => 'while and do/while are out of the grammar (Chad, 2026-10-21); loop with foreach or for',
            default => $type . ' is outside the template grammar, which refuses by default',
        };
    }
}
