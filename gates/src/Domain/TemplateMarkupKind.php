<?php

declare(strict_types=1);

namespace Gates\Domain;

/** Lexical HTML contexts determine where a morph comment can be emitted. */
enum TemplateMarkupKind
{
    case Body;
    case OpenTag;
    case Tag;
    case TagName;
    case SingleQuoted;
    case DoubleQuoted;
    case Comment;
    case Script;
    case Style;
    case Textarea;
    case Title;
}
