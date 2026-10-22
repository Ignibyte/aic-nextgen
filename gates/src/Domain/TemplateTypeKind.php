<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One kind a template expression's static type may hold (#97). A type is a
 * set of them; `Object` carries its class.
 */
enum TemplateTypeKind
{
    case String;
    case Int;
    case Float;
    case Bool;
    case Null;
    case List;
    case Object;
    case Unknown;
}
