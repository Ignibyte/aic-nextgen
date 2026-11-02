<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

use function array_filter;
use function array_values;
use function count;

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

    /**
     * @param Node|null $node  A declared type.
     * @param string    $class The class-like it sits in.
     *
     * @return string The one class it names, nullable or beside `null`; `self` and `static` as the class; empty otherwise (#232).
     */
    public function typeName(?Node $node, string $class): string
    {
        $inner = match (true) {
            $node instanceof NullableType => $node->type,
            $node instanceof UnionType => self::nullable($node),
            default => $node,
        };

        return $inner instanceof Name
            ? $this->className($inner, $class)
            : '';
    }

    /**
     * @param UnionType $unionType A union.
     *
     * @return Node|null Its one named type when the other is a keyword (`null`); null otherwise.
     */
    private static function nullable(UnionType $unionType): ?Node
    {
        $named = array_values(array_filter($unionType->types, static fn (Node $node): bool => !$node instanceof Identifier));

        return count($named) === 1 && count($unionType->types) === 2
            ? $named[0]
            : null;
    }
}
