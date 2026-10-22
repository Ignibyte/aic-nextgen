<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use PhpParser\Node\Name;

/**
 * Reads php-parser's names for the code index (#106).
 */
final readonly class NameReader
{
    /**
     * @param Name|null $name A declaration's resolved name.
     *
     * @return string It; empty for an anonymous class.
     */
    public function declared(?Name $name): string
    {
        return $name instanceof Name
            ? $name->toString()
            : '';
    }

    /**
     * @param Name   $name  A class name.
     * @param string $class The class-like the name sits in.
     *
     * @return string It resolved; `self` and `static` as that class; empty for `parent`.
     */
    public function className(Name $name, string $class): string
    {
        return match ($name->toLowerString()) {
            'self', 'static' => $class,
            'parent' => '',
            default => $name->toString(),
        };
    }
}
