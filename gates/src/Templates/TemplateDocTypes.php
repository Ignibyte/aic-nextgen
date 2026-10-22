<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use InvalidArgumentException;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use ReflectionClass;
use ReflectionProperty;

use function array_key_exists;
use function end;
use function file_get_contents;
use function in_array;
use function is_string;
use function ltrim;
use function str_starts_with;
use function strtolower;

/**
 * Reads a list property's element type for the build (#97): the promoting
 * constructor's `@param list<T> $name` (or `array<K, T>`). `T` is a class or
 * `string`, `int`, `float` or `bool`; a class name resolves as PHP resolves
 * it in the class's file. Anything else cannot be looped over.
 */
final readonly class TemplateDocTypes
{
    /**
     * The scalar element types a loop accepts.
     */
    private const array SCALARS = [
        'bool' => TemplateTypeKind::Bool,
        'float' => TemplateTypeKind::Float,
        'int' => TemplateTypeKind::Int,
        'string' => TemplateTypeKind::String,
    ];

    /**
     * @param TemplateDocParser $templateDocParser Reads the constructor's docblock.
     */
    public function __construct(
        private TemplateDocParser $templateDocParser = new TemplateDocParser(),
    ) {
    }

    /**
     * @param ReflectionProperty $reflectionProperty A public array property.
     *
     * @throws InvalidArgumentException When no `@param` gives it a list of one class or scalar.
     *
     * @return TemplateType The list's element type.
     */
    public function element(ReflectionProperty $reflectionProperty): TemplateType
    {
        $typeNode = $this->templateDocParser->promotedType($reflectionProperty);
        $element = $typeNode instanceof GenericTypeNode && in_array($typeNode->type->name, ['list', 'array', 'non-empty-list'], true)
            ? end($typeNode->genericTypes)
            : null;

        if (!$element instanceof IdentifierTypeNode) {
            throw new InvalidArgumentException(TemplateRefusal::LoopType->value . ': ' . $reflectionProperty->getName()
                . ' is promoted with a @param list<T>, T a class or string, int, float or bool');
        }

        $name = strtolower($element->name);

        return array_key_exists($name, self::SCALARS)
            ? new TemplateType([self::SCALARS[$name]])
            : new TemplateType([TemplateTypeKind::Object], $this->resolve($element->name, $reflectionProperty->getDeclaringClass()));
    }

    /**
     * @param string                  $name            A class name as the docblock writes it.
     * @param ReflectionClass<object> $reflectionClass The class whose file the docblock is in.
     *
     * @return string The class's full name, by the file's namespace and `use` lines.
     */
    private function resolve(string $name, ReflectionClass $reflectionClass): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $file = $reflectionClass->getFileName();
        $source = is_string($file)
            ? file_get_contents($file)
            : false;
        $nameResolver = new NameResolver();
        (new NodeTraverser($nameResolver))->traverse(
            is_string($source) ? ((new ParserFactory())->createForHostVersion()->parse($source) ?? []) : [],
        );

        return $nameResolver->getNameContext()->getResolvedClassName(new Name($name))->toString();
    }
}
