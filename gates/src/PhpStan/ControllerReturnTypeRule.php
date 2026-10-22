<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

use function preg_match;
use function sprintf;
use function str_replace;

/**
 * A controller's methods return its kind's response (gate:8, #99): in a
 * module's `src/Controller/`, every method but the constructor of a Page, Api
 * or Stream returns that kind's sealed response, read from its phpdoc. A
 * helper returning anything else, `never` and `void` included, belongs in the
 * module's `src/Service/`.
 *
 * @implements Rule<InClassMethodNode>
 */
final readonly class ControllerReturnTypeRule implements Rule
{
    /**
     * Each kind's response, by the kind.
     */
    private const array RESPONSES = [
        Api::class => ApiResponse::class,
        Page::class => PageResponse::class,
        Stream::class => StreamResponse::class,
    ];

    /**
     * @return class-string<InClassMethodNode> The node this rule inspects.
     */
    #[Override]
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /**
     * @param InClassMethodNode $node  A method, in its class.
     * @param Scope             $scope Where it is.
     *
     * @return list<IdentifierRuleError> One error when a controller's method returns something but its kind's response.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $phpMethodFromParserNodeReflection = $node->getMethodReflection();
        $response = self::response($node);

        if (
            $response === ''
            || $phpMethodFromParserNodeReflection->getName() === '__construct'
            || !self::inController($scope->getFile())
        ) {
            return [];
        }

        $returnType = $phpMethodFromParserNodeReflection->getOnlyVariant()->getReturnType();

        if ($returnType->isObject()->yes() && (new ObjectType($response))->isSuperTypeOf($returnType)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                "A controller returns its kind's response (#99): %s() does not return a %s; move the helper to the module's src/Service/.",
                $phpMethodFromParserNodeReflection->getName(),
                $response,
            ))->identifier('aic.controllerReturnType')->build(),
        ];
    }

    /**
     * @param InClassMethodNode $inClassMethodNode A method, in its class.
     *
     * @return string Its class's kind's response; empty when the class is no Page, Api or Stream.
     */
    private static function response(InClassMethodNode $inClassMethodNode): string
    {
        $classReflection = $inClassMethodNode->getClassReflection();

        foreach (self::RESPONSES as $kind => $response) {
            if ($classReflection->implementsInterface($kind)) {
                return $response;
            }
        }

        return '';
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
}
