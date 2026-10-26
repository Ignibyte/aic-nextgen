<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * What the HTML lint refuses (#170), each case the name gate:25 and gate:23
 * print. Style belongs in the theme's stylesheets and script in script files
 * (Chad, 2026-10-26: "No inline styles (outside of javascript)").
 */
enum HtmlRule: string
{
    case InlineStyle = 'inline style';
    case InlineScript = 'inline script';
    case AttributeName = 'attribute name';
    case AttributeQuote = 'attribute quote';
    case TagSpacing = 'tag spacing';
    case DuplicateId = 'duplicate id';
    case MissingAlt = 'missing alt';
    case ParseError = 'parse error';
}
