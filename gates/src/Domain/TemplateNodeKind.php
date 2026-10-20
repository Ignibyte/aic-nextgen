<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * What one node of a parsed component template is.
 */
enum TemplateNodeKind
{
    /** Markup written as it is. */
    case Text;

    /** `{{ expr }}`: a string, escaped. */
    case Escaped;

    /** `{!! expr !!}`: an Html, sent as it is. */
    case Raw;

    /** `@component(expr)`: a component, rendered. */
    case Component;

    /** `@if (cond)` ... `@else` ... `@endif`. */
    case Condition;

    /** `@foreach (expr as Type $var)` ... `@endforeach`. */
    case Loop;

    /** `@use Name`: an import for the generated class. */
    case Import;
}
