<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantStringType;

use function array_map;

/**
 * What a `new` may build, for the construction rules (gate:8, #99, #310): the
 * resolved name, or the class-strings and objects its expression holds, so an
 * alias or a class-string in a variable is read too.
 */
final readonly class BuiltClasses
{
    /**
     * @param New_  $new   A `new`.
     * @param Scope $scope Where it is.
     *
     * @return list<string> The classes it may build.
     */
    public function named(New_ $new, Scope $scope): array
    {
        if ($new->class instanceof Name) {
            return [$scope->resolveName($new->class)];
        }

        if (!$new->class instanceof Expr) {
            return [];
        }

        $type = $scope->getType($new->class);

        return [
            ...array_map(
                static fn (ConstantStringType $constantStringType): string => $constantStringType->getValue(),
                $type->getConstantStrings(),
            ),
            ...$type->getObjectClassNames(),
        ];
    }
}
