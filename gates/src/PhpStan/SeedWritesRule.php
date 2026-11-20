<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Controller\Contracts\Command;
use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedParametersAcceptor;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_filter;
use function array_values;
use function sprintf;
use function str_ends_with;
use function str_starts_with;

/**
 * A seed writes only through repositories (gate:8, #320): a `Command` whose
 * name ends in `Seed` takes nothing from `AIC\Database` or `AIC\Entity` in
 * its constructor, so its rows reach the table through a module repository,
 * which holds the table's fields and its unique column.
 *
 * @implements Rule<InClassMethodNode>
 */
final readonly class SeedWritesRule implements Rule
{
    /**
     * The namespaces that write rows, which a seed may not take.
     */
    private const array WRITERS = ['AIC\\Database\\', 'AIC\\Entity\\'];

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
     * @return list<IdentifierRuleError> One error per parameter of a seed's constructor typed by a row writer.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $phpMethodFromParserNodeReflection = $node->getMethodReflection();
        $classReflection = $scope->getClassReflection();

        if (
            !$classReflection instanceof ClassReflection
            || $phpMethodFromParserNodeReflection->getName() !== '__construct'
            || !self::isSeed($classReflection)
        ) {
            return [];
        }

        return self::errors($classReflection->getName(), $phpMethodFromParserNodeReflection->getOnlyVariant());
    }

    /**
     * @param string                     $seed                       The seed.
     * @param ExtendedParametersAcceptor $extendedParametersAcceptor Its constructor's signature.
     *
     * @return list<IdentifierRuleError> One error per parameter typed by a row writer.
     */
    private static function errors(string $seed, ExtendedParametersAcceptor $extendedParametersAcceptor): array
    {
        $errors = [];

        foreach ($extendedParametersAcceptor->getParameters() as $extendedParameterReflection) {
            foreach (self::writers($extendedParameterReflection->getType()->getReferencedClasses()) as $writer) {
                $errors[] = self::error($seed, $extendedParameterReflection->getName(), $writer);
            }
        }

        return $errors;
    }

    /**
     * @param ClassReflection $classReflection The method's class.
     *
     * @return bool Whether it is a seed: a command whose name ends in `Seed`.
     */
    private static function isSeed(ClassReflection $classReflection): bool
    {
        return $classReflection->implementsInterface(Command::class) && str_ends_with($classReflection->getName(), 'Seed');
    }

    /**
     * @param list<string> $classes The classes a parameter's type names.
     *
     * @return list<string> Those in a namespace that writes rows.
     */
    private static function writers(array $classes): array
    {
        return array_values(array_filter(
            $classes,
            static fn (string $class): bool => array_filter(
                self::WRITERS,
                static fn (string $writer): bool => str_starts_with($class, $writer),
            ) !== [],
        ));
    }

    /**
     * @param string $seed      The seed.
     * @param string $parameter The parameter.
     * @param string $writer    The class it names.
     *
     * @return IdentifierRuleError The error.
     */
    private static function error(string $seed, string $parameter, string $writer): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf(
            'A seed writes only through repositories (#320): %s takes $%s, typed %s; take the module repository that saves the rows.',
            $seed,
            $parameter,
            $writer,
        ))->identifier('aic.seedWrites')->build();
    }
}
