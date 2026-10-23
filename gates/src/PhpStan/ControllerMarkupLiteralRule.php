<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use Override;
use PhpParser\Node;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar as ScalarNode;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_any;
use function preg_match;
use function str_contains;
use function str_replace;

/**
 * A controller carries no markup (gate:8, #99): a string with `<` in a
 * module's `src/Controller/` is refused, quoted, heredoc, nowdoc, escaped
 * (`"\x3c"`) or a constant alike, since the rule reads php-parser's decoded
 * value. Markup belongs in a component's template.
 *
 * @implements Rule<ScalarNode>
 */
final readonly class ControllerMarkupLiteralRule implements Rule
{
    /**
     * @return class-string<ScalarNode> The node this rule inspects.
     */
    #[Override]
    public function getNodeType(): string
    {
        return ScalarNode::class;
    }

    /**
     * @param ScalarNode $node  A literal.
     * @param Scope      $scope Where it is.
     *
     * @return list<IdentifierRuleError> One error when a controller's string holds `<`.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!self::inController($scope->getFile()) || !self::holdsMarkup($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('A controller carries no markup (#99): a string here holds "<"; write it in a component\'s template.')
                ->identifier('aic.controllerMarkupLiteral')
                ->build(),
        ];
    }

    /**
     * @param string $file A file's path.
     *
     * @return bool Whether it is in a module's `src/Controller/`.
     */
    private static function inController(string $file): bool
    {
        return preg_match('#/modules/[^/]+/src/Controller/#', str_replace('\\', '/', $file)) === 1;
    }

    /**
     * @param ScalarNode $scalarNode A literal.
     *
     * @return bool Whether its decoded text, or one of its interpolation's parts, holds `<`.
     */
    private static function holdsMarkup(ScalarNode $scalarNode): bool
    {
        if ($scalarNode instanceof String_) {
            return str_contains($scalarNode->value, '<');
        }

        return $scalarNode instanceof InterpolatedString && array_any(
            $scalarNode->parts,
            static fn (Node $part): bool => $part instanceof InterpolatedStringPart && str_contains($part->value, '<'),
        );
    }
}
