<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Schema\Application\PostgresExpressions;
use AIC\Schema\Contracts\Predicate;
use AIC\Schema\Contracts\Term;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_intersect;

/**
 * Only the expression factory writes a schema declaration's SQL (gate:8,
 * #310): `new Term` or `new Predicate` anywhere but
 * `AIC\Schema\Application\PostgresExpressions` is refused, through an alias
 * or a class-string held in a variable too, so a module cannot hand a check,
 * a partial index or a generated column raw SQL.
 *
 * @implements Rule<New_>
 */
final readonly class SchemaExpressionRule implements Rule
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
     * @return list<IdentifierRuleError> One error when it builds a Term or a Predicate outside the factory.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        if (
            array_intersect([Term::class, Predicate::class], (new BuiltClasses())->named($node, $scope)) === []
            || $classReflection !== null
            && $classReflection->getName() === PostgresExpressions::class
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Only ' . PostgresExpressions::class . ' makes a Term or a Predicate (#310); take them from Schema::expressions().',
            )
                ->identifier('aic.schemaExpression')
                ->build(),
        ];
    }
}
