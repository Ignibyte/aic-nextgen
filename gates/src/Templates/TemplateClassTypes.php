<?php

declare(strict_types=1);

namespace Gates\Templates;

use AIC\Component\Contracts\Component;
use Gates\Domain\RegexCaptures;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function array_key_exists;
use function basename;
use function class_exists;
use function enum_exists;
use function explode;
use function get_debug_type;
use function in_array;
use function interface_exists;
use function is_a;
use function str_replace;
use function ucwords;

/**
 * What reflection tells the build about a template's classes (#97): the
 * component the typing line names, a public property's type, a class
 * constant's or enum case's type. Each refusal is `category: detail`.
 */
final readonly class TemplateClassTypes
{
    use RegexCaptures;

    /**
     * Builtin type names, by the kind they are.
     */
    private const array BUILTINS = [
        'bool' => TemplateTypeKind::Bool,
        'false' => TemplateTypeKind::Bool,
        'float' => TemplateTypeKind::Float,
        'int' => TemplateTypeKind::Int,
        'null' => TemplateTypeKind::Null,
        'string' => TemplateTypeKind::String,
        'true' => TemplateTypeKind::Bool,
    ];

    /**
     * @param TemplateDocTypes $templateDocTypes Reads list element types.
     */
    public function __construct(
        private TemplateDocTypes $templateDocTypes = new TemplateDocTypes(),
    ) {
    }

    /**
     * @param string $component The class the typing line names.
     * @param string $path      The template, relative to the repo root.
     *
     * @throws InvalidArgumentException When it is not a final component class, or not the module's own of the file's name.
     *
     * @return string The file the class is declared in.
     */
    public function component(string $component, string $path): string
    {
        if (
            !class_exists($component)
            || !(new ReflectionClass($component))->isFinal()
            || !is_a($component, Component::class, true)
        ) {
            throw new InvalidArgumentException(
                TemplateRefusal::ComponentType->value . ': ' . $component . ' is not a final class implementing ' . Component::class,
            );
        }

        $this->owned($component, $path);

        return (string) (new ReflectionClass($component))->getFileName();
    }

    /**
     * @param TemplateType $templateType An object's type.
     * @param string       $name         A property's name.
     *
     * @throws InvalidArgumentException When it is not one class with a public, declared, typed property of that name.
     *
     * @return TemplateType The property's type; an enum's `name` is a string and its `value` its backing type.
     */
    public function property(TemplateType $templateType, string $name): TemplateType
    {
        $class = $this->owner($templateType, $name);

        if (enum_exists($class) && in_array($name, ['name', 'value'], true)) {
            return $this->enumMember((new ReflectionEnum($class))->getBackingType(), $name);
        }

        $reflectionProperty = $this->declaredProperty($class, $name);

        if (!$reflectionProperty->isPublic()) {
            throw new InvalidArgumentException(
                TemplateRefusal::Property->value . ': ' . $class . '::$' . $name . ' is not a public property',
            );
        }

        return $this->reflected($reflectionProperty);
    }

    /**
     * @param string $class A class's full name.
     * @param string $name  A constant or enum case.
     *
     * @throws InvalidArgumentException When the class or its constant does not exist.
     *
     * @return TemplateType The case's enum, or the constant's value type.
     */
    public function constant(string $class, string $name): TemplateType
    {
        if (enum_exists($class) && (new ReflectionEnum($class))->hasCase($name)) {
            return new TemplateType([TemplateTypeKind::Object], $class);
        }

        if (!class_exists($class) && !interface_exists($class) || !(new ReflectionClass($class))->hasConstant($name)) {
            throw new InvalidArgumentException(TemplateRefusal::ClassName->value . ': ' . $class . '::' . $name . ' does not exist');
        }

        return new TemplateType([$this->constantKind($class, $name)]);
    }

    /**
     * @param class-string $component The validated component class.
     * @param string       $path      Its template's relative path.
     *
     * @throws InvalidArgumentException When the template names a different module's component.
     */
    private function owned(string $component, string $path): void
    {
        $match = self::regexMatch('#^modules/([a-z0-9_]+)/templates/components/#', $path);

        if (!array_key_exists(1, $match)) {
            return;
        }

        $expected = 'App\\' . str_replace('_', '', ucwords($match[1], '_')) . '\\Component\\' . basename($path, '.html.php');

        if ($component !== $expected) {
            throw new InvalidArgumentException(
                TemplateRefusal::ComponentType->value . ": a component template renders its module's own " . $expected,
            );
        }
    }

    /**
     * @param class-string $class The existing owning class.
     * @param string       $name  A declared property's name.
     *
     * @throws InvalidArgumentException When reflection cannot find the property.
     *
     * @return ReflectionProperty The property to inspect.
     */
    private function declaredProperty(string $class, string $name): ReflectionProperty
    {
        try {
            return (new ReflectionClass($class))->getProperty($name);
        } catch (ReflectionException $reflectionException) {
            throw new InvalidArgumentException(
                TemplateRefusal::Property->value . ': ' . $class . '::$' . $name . ' is not a public property',
                0,
                $reflectionException,
            );
        }
    }

    /**
     * @param class-string $class The owning class or interface.
     * @param string       $name  Its constant's name.
     *
     * @throws InvalidArgumentException When reflection cannot read the constant.
     *
     * @return TemplateTypeKind The constant's value type, or unknown for an unsupported value.
     */
    private function constantKind(string $class, string $name): TemplateTypeKind
    {
        try {
            $type = get_debug_type((new ReflectionClass($class))->getConstant($name));
        } catch (ReflectionException $reflectionException) {
            throw new InvalidArgumentException(
                TemplateRefusal::ClassName->value . ': ' . $class . '::' . $name . ' does not exist',
                0,
                $reflectionException,
            );
        }

        return self::BUILTINS[explode('|', $type)[0]] ?? TemplateTypeKind::Unknown;
    }

    /**
     * @param TemplateType $templateType An object's type.
     * @param string       $name         The property read on it.
     *
     * @throws InvalidArgumentException When it is not one class or interface.
     *
     * @return class-string The class.
     */
    private function owner(TemplateType $templateType, string $name): string
    {
        $class = $templateType->class;

        if (
            !$templateType->withoutNull()->only(TemplateTypeKind::Object)
            || !class_exists($class) && !interface_exists($class)
        ) {
            throw new InvalidArgumentException(TemplateRefusal::Property->value . ': ->' . $name . ' reads a value that is not one class');
        }

        return $class;
    }

    /**
     * @param ReflectionProperty $reflectionProperty A public property.
     *
     * @throws InvalidArgumentException When it is untyped, or an array property has no list docblock.
     *
     * @return TemplateType Its type.
     */
    private function reflected(ReflectionProperty $reflectionProperty): TemplateType
    {
        $reflectionType = $reflectionProperty->getType() ?? throw new InvalidArgumentException(
            TemplateRefusal::Property->value . ': $' . $reflectionProperty->getName() . ' is not typed',
        );
        $named = $reflectionType instanceof ReflectionUnionType
            ? $reflectionType->getTypes()
            : [$reflectionType];
        $templateType = new TemplateType([]);

        foreach ($named as $type) {
            $templateType = $templateType->join($this->named($type, $reflectionProperty));
        }

        return $reflectionType->allowsNull()
            ? $templateType->join(new TemplateType([TemplateTypeKind::Null]))
            : $templateType;
    }

    /**
     * @param ReflectionType     $reflectionType     One type of a declaration.
     * @param ReflectionProperty $reflectionProperty The property, for a list's element type.
     *
     * @throws InvalidArgumentException When an array property has no list docblock.
     *
     * @return TemplateType The type.
     */
    private function named(ReflectionType $reflectionType, ReflectionProperty $reflectionProperty): TemplateType
    {
        if (!$reflectionType instanceof ReflectionNamedType) {
            return new TemplateType([TemplateTypeKind::Unknown]);
        }

        $name = $reflectionType->getName();

        return match (true) {
            $name === 'array' => new TemplateType([TemplateTypeKind::List], '', $this->templateDocTypes->element($reflectionProperty)),
            !$reflectionType->isBuiltin() => new TemplateType([TemplateTypeKind::Object], $name),
            default => new TemplateType([self::BUILTINS[$name] ?? TemplateTypeKind::Unknown]),
        };
    }

    /**
     * @param ReflectionType|null $reflectionType The enum's backing type, or null when unbacked.
     * @param string              $name           `name` or `value`.
     *
     * @return TemplateType A string for `name`; the backing type for `value`.
     */
    private function enumMember(?ReflectionType $reflectionType, string $name): TemplateType
    {
        if ($name === 'name' || !$reflectionType instanceof ReflectionNamedType) {
            return new TemplateType([$name === 'name' ? TemplateTypeKind::String : TemplateTypeKind::Unknown]);
        }

        return new TemplateType([self::BUILTINS[$reflectionType->getName()] ?? TemplateTypeKind::Unknown]);
    }
}
