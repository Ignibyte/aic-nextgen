<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Container\Contracts\ServiceRegistry;
use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_any;
use function array_key_exists;
use function count;
use function sprintf;

/**
 * A plugin implements its type (gate:8): in `$registry->tag(Type::class,
 * Service::class)` on a ServiceRegistry, the service class must implement the
 * type. It replaces old AIC's `#[Plugin]` type-match rule, and it is why tags
 * are interface names.
 *
 * @implements Rule<MethodCall>
 */
final readonly class TaggedServiceTypeRule implements Rule
{
    /**
     * @param ReflectionProvider $reflectionProvider Reads the classes named in the call.
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    /**
     * @return class-string<MethodCall> The node this rule inspects.
     */
    #[Override]
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node  A method call.
     * @param Scope      $scope Where it is.
     *
     * @return list<IdentifierRuleError> One error when a tagged service does not implement its type.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isTagCall($node, $scope)) {
            return [];
        }

        $args = $node->getArgs();

        if (!array_key_exists(0, $args) || !array_key_exists(1, $args)) {
            return [];
        }

        $type = self::className($args[0], $scope);
        $service = self::className($args[1], $scope);

        if ($type === '' || $service === '' || $this->implementsType($service, $type)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf('Plugin %s is tagged %s but does not implement it.', $service, $type))
                ->identifier('aic.taggedServiceType')
                ->build(),
        ];
    }

    /**
     * @param MethodCall $methodCall A method call.
     * @param Scope      $scope      Where it is.
     *
     * @return bool Whether it is `tag()` with two arguments on a ServiceRegistry.
     */
    private function isTagCall(MethodCall $methodCall, Scope $scope): bool
    {
        if (
            !$methodCall->name instanceof Identifier
            || $methodCall->name->toString() !== 'tag'
            || count($methodCall->getArgs()) < 2
        ) {
            return false;
        }

        return array_any(
            $scope->getType($methodCall->var)->getObjectClassNames(),
            fn (string $className): bool => $className === ServiceRegistry::class
                || $this->implementsType($className, ServiceRegistry::class),
        );
    }

    /**
     * @param string $className A class.
     * @param string $type      An interface.
     *
     * @return bool Whether the class implements the interface; true for an unknown class,
     *              which is not this rule's to judge.
     */
    private function implementsType(string $className, string $type): bool
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return true;
        }

        return $this->reflectionProvider->getClass($className)->implementsInterface($type);
    }

    /**
     * @param Arg   $arg   An argument.
     * @param Scope $scope Where it is.
     *
     * @return string The class a `Name::class` names; empty for anything else.
     */
    private static function className(Arg $arg, Scope $scope): string
    {
        $value = $arg->value;

        if (
            !$value instanceof ClassConstFetch
            || !$value->class instanceof Name
            || !$value->name instanceof Identifier
        ) {
            return '';
        }

        return $value->name->toString() === 'class'
            ? $scope->resolveName($value->class)
            : '';
    }
}
