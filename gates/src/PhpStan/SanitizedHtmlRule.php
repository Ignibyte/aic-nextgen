<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Component\Application\HtmlSanitizer;
use AIC\Component\Domain\SanitizedHtml;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function in_array;

/**
 * Only the sanitizer vouches for markup (gate:8, #337): `new SanitizedHtml`
 * anywhere but `AIC\Component\Application\HtmlSanitizer` is refused, through
 * an alias or a class-string held in a variable too, so a theme component's
 * RawHtml grant for sanitized markup cannot be handed a visitor's input as it
 * arrived.
 *
 * @implements Rule<New_>
 */
final readonly class SanitizedHtmlRule implements Rule
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
     * @return list<IdentifierRuleError> One error when it builds a SanitizedHtml outside the sanitizer.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        if (
            !in_array(SanitizedHtml::class, (new BuiltClasses())->named($node, $scope), true)
            || $classReflection !== null && $classReflection->getName() === HtmlSanitizer::class
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Only ' . HtmlSanitizer::class . ' makes a SanitizedHtml (#337); sanitize the markup with it.',
            )
                ->identifier('aic.sanitizedHtml')
                ->build(),
        ];
    }
}
