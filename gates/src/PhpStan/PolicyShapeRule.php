<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Access\Contracts\Policy;
use AIC\Access\Domain\Actor;
use AIC\Access\Domain\Decision;
use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedParametersAcceptor;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

use function count;
use function sprintf;

/**
 * A policy's actions keep the contract's shape (gate:8, #304): every public
 * method of a `Policy` but the constructor takes an `Actor`, and one record
 * object when there is one (not for `create`), and returns a `Decision`. A
 * helper belongs in a private method or a service.
 *
 * @implements Rule<InClassMethodNode>
 */
final readonly class PolicyShapeRule implements Rule
{
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
     * @param Scope             $scope Where it is: its class.
     *
     * @return list<IdentifierRuleError> One error when a policy's public method is not `(Actor, Subject): Decision`.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $phpMethodFromParserNodeReflection = $node->getMethodReflection();
        $name = $phpMethodFromParserNodeReflection->getName();
        $classReflection = $scope->getClassReflection();

        if (
            !$classReflection instanceof ClassReflection
            || !$phpMethodFromParserNodeReflection->isPublic()
            || !self::isAction($classReflection, $name)
        ) {
            return [];
        }

        $extendedParametersAcceptor = $phpMethodFromParserNodeReflection->getOnlyVariant();

        return self::shaped($extendedParametersAcceptor)
            ? []
            : [self::error($classReflection->getName() . '::' . $name)];
    }

    /**
     * @param ClassReflection $classReflection The method's class.
     * @param string          $name            The method's name.
     *
     * @return bool Whether it is a policy's method other than its constructor.
     */
    private static function isAction(ClassReflection $classReflection, string $name): bool
    {
        return $classReflection->implementsInterface(Policy::class) && $name !== '__construct';
    }

    /**
     * @param ExtendedParametersAcceptor $extendedParametersAcceptor The method's signature.
     *
     * @return bool Whether it takes an `Actor` and at most one object, and returns a `Decision`.
     */
    private static function shaped(ExtendedParametersAcceptor $extendedParametersAcceptor): bool
    {
        $parameters = $extendedParametersAcceptor->getParameters();

        $actor = $parameters[0] ?? null;
        $record = $parameters[1] ?? null;

        return $actor !== null
            && count($parameters) <= 2
            && (new ObjectType(Actor::class))->equals($actor->getType())
            && ($record === null || $record->getType()->isObject()->yes())
            && (new ObjectType(Decision::class))->equals($extendedParametersAcceptor->getReturnType());
    }

    /**
     * @param string $where `Class::method`.
     *
     * @return IdentifierRuleError The error.
     */
    private static function error(string $where): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf(
            'A policy action is (Actor): Decision or (Actor, Subject): Decision (#304): %s is not; a helper belongs in a private method.',
            $where,
        ))->identifier('aic.policyShape')->build();
    }
}
