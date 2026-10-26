<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Why gate:25 refuses a construct in a component template (#96): one category
 * per line it prints. The grammar is plain PHP read only, deny by default
 * (Chad, 2026-10-21): anything outside it is `not in the grammar`. The build
 * (#97) adds the type categories: what the grammar allows but a static type
 * forbids (an echo of a bool, a condition that is not one, a null test on a
 * value that cannot be null). The HTML lint (#170) adds `html`, the markup
 * rules, and `whitespace`, the line rules.
 */
enum TemplateRefusal: string
{
    case Syntax = 'syntax';
    case Placement = 'placement';
    case ComponentType = 'component type';
    case ShortOpenTag = 'short open tag';
    case FunctionCall = 'function call';
    case MethodCall = 'method call';
    case StaticCall = 'static call';
    case NewObject = 'new';
    case Closure = 'closure';
    case Assignment = 'assignment';
    case IncludeRequire = 'include/require';
    case EvalCall = 'eval';
    case ExitCall = 'exit';
    case EchoPrint = 'echo/print';
    case PhpBlock = 'php block';
    case InstanceofTest = 'instanceof';
    case GlobalVariable = 'global';
    case StaticVariable = 'static';
    case Superglobal = 'superglobal';
    case VariableVariable = 'variable variable';
    case Backtick = 'backtick';
    case Suppress = '@';
    case Declaration = 'declaration';
    case ControlSyntax = 'control syntax';
    case NotInScope = 'not in scope';
    case NotInGrammar = 'not in the grammar';
    case ClassName = 'class name';
    case Property = 'property';
    case LoopType = 'loop type';
    case ConditionType = 'condition type';
    case EchoType = 'echo type';
    case ComponentKey = 'component key';
    case NotCompilable = 'not compilable';
    case Html = 'html';
    case Whitespace = 'whitespace';
}
