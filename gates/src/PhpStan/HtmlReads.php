<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Component\Contracts\Html;
use AIC\Controller\Application\Psr7ResponseWriter;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function in_array;

/**
 * The check behind both markup-read rules (gate:8, #99): `markup()` called on
 * an Html anywhere but `Psr7ResponseWriter`, the one reader.
 */
final readonly class HtmlReads
{
    /**
     * @param Expr            $expr  What the method is called on.
     * @param Identifier|Expr $name  The method's name.
     * @param Scope           $scope Where the call is.
     *
     * @return list<IdentifierRuleError> One error when it reads an Html's markup outside the writer.
     */
    public function errors(Expr $expr, Identifier|Expr $name, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        if (
            !$name instanceof Identifier
            || $name->toLowerString() !== 'markup'
            || !in_array(Html::class, $scope->getType($expr)->getObjectClassNames(), true)
            || $classReflection !== null && $classReflection->getName() === Psr7ResponseWriter::class
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Only ' . Psr7ResponseWriter::class . ' reads an Html (#99); pass the Layout to the writer.')
                ->identifier('aic.htmlMarkupRead')
                ->build(),
        ];
    }
}
