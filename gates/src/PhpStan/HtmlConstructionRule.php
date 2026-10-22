<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Component\Application\Renderer;
use AIC\Component\Contracts\Html;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;

use function array_map;
use function in_array;

/**
 * Only the Renderer makes HTML (gate:8, #99): `new Html` anywhere but
 * `AIC\Component\Application\Renderer` is refused, through an alias or a
 * class-string held in a variable too, since the rule reads the resolved name
 * and the expression's type.
 *
 * @implements Rule<New_>
 */
final readonly class HtmlConstructionRule implements Rule
{
    /**
     * @return class-string<New_> The node this rule inspects.
     */
    #[Override]
    public function getNodeType(): string
    {
        return New_::class;
    }

    /**
     * @param New_  $node  A `new`.
     * @param Scope $scope Where it is.
     *
     * @return list<IdentifierRuleError> One error when it builds an Html outside the Renderer.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        if (
            !in_array(Html::class, self::built($node, $scope), true)
            || $classReflection !== null
            && $classReflection->getName() === Renderer::class
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Only ' . Renderer::class . ' makes an Html (#99); hand the Renderer components instead.')
                ->identifier('aic.htmlConstruction')
                ->build(),
        ];
    }

    /**
     * @param New_  $new   A `new`.
     * @param Scope $scope Where it is.
     *
     * @return list<string> The classes it may build: the resolved name, or the class-strings and objects its expression holds.
     */
    private static function built(New_ $new, Scope $scope): array
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
