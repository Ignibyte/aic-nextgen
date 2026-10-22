<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Block\Contracts\Block;
use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_filter;
use function array_values;
use function in_array;
use function sprintf;

/**
 * The render tree takes data, never markup (gate:8, #99): a public method of
 * `Region`, `Regions` or a `Layout` takes only components, blocks, regions,
 * enums and scalars, which templates escape, in its parameters and their
 * generics, promoted ones included. An `Html`, or any other class, is refused.
 *
 * @implements Rule<InClassMethodNode>
 */
final readonly class RenderTreeInputRule implements Rule
{
    /**
     * The classes a render-tree input may name besides components and enums.
     */
    private const array ALLOWED = [Block::class, Region::class, Regions::class];

    /**
     * @param ReflectionProvider $reflectionProvider Reads the classes a parameter names.
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

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
     * @return list<IdentifierRuleError> One error per parameter that names a class the render tree may not take.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $phpMethodFromParserNodeReflection = $node->getMethodReflection();

        if (!$phpMethodFromParserNodeReflection->isPublic() || !self::inRenderTree($scope)) {
            return [];
        }

        $errors = [];

        foreach ($phpMethodFromParserNodeReflection->getOnlyVariant()->getParameters() as $extendedParameterReflection) {
            foreach ($this->refused($extendedParameterReflection->getType()->getReferencedClasses()) as $class) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'The render tree takes data, never markup (#99): %s() parameter $%s names %s; pass a component.',
                    $phpMethodFromParserNodeReflection->getName(),
                    $extendedParameterReflection->getName(),
                    $class,
                ))->identifier('aic.renderTreeInput')->build();
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $classes The classes a parameter's type names, generics included.
     *
     * @return list<string> Those that are no component, block, region or enum.
     */
    private function refused(array $classes): array
    {
        return array_values(array_filter(
            $classes,
            fn (string $class): bool => !in_array($class, self::ALLOWED, true) && !$this->isData($class),
        ));
    }

    /**
     * @param string $class A class a parameter names.
     *
     * @return bool Whether it is a component or an enum: data a template escapes.
     */
    private function isData(string $class): bool
    {
        if (!$this->reflectionProvider->hasClass($class)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($class);

        return $classReflection->isEnum() || $class === Component::class || $classReflection->implementsInterface(Component::class);
    }

    /**
     * @param Scope $scope Where a method is.
     *
     * @return bool Whether it is in `Region`, `Regions` or a `Layout`.
     */
    private static function inRenderTree(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        return $classReflection !== null && self::isRenderTree($classReflection);
    }

    /**
     * @param ClassReflection $classReflection A class.
     *
     * @return bool Whether it is `Region`, `Regions` or a `Layout`.
     */
    private static function isRenderTree(ClassReflection $classReflection): bool
    {
        return in_array($classReflection->getName(), [Region::class, Regions::class], true)
            || $classReflection->implementsInterface(Layout::class);
    }
}
