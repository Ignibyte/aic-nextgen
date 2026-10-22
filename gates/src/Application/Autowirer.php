<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Attribute\Tagged;
use Gates\Domain\WiredObject;
use Gates\Domain\WiringScope;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

use function array_key_exists;
use function class_exists;
use function in_array;
use function interface_exists;
use function sprintf;

/**
 * Resolves a module class's constructor at build time (#92, #93), by
 * reflection, into the object graph the generated wiring builds.
 *
 * A parameter is, in this order: a plugin list marked `#[Tagged(Type::class)]`;
 * a root of the side being wired, by name (`string $bootedAt`, `array
 * $schedules`) or by type (the app's settings, the database, …); a type a
 * module factory makes; an interface bound by the module or a module it
 * requires; or a readonly class the scope reaches, built the same way.
 * Trailing parameters with defaults keep them. Anything else is refused with
 * the module, the class and the parameter. Only readonly classes are built, so
 * sharing one instance between classes is never observable.
 */
final readonly class Autowirer
{
    /**
     * @param class-string $class       The class to build.
     * @param WiringScope  $wiringScope What it may be built from.
     * @param list<string> $path        The classes being built around it, to find a cycle.
     *
     * @throws InvalidArgumentException When a parameter cannot be wired.
     *
     * @return WiredObject The class and its arguments.
     */
    public function wire(string $class, WiringScope $wiringScope, array $path = []): WiredObject
    {
        if (in_array($class, $path, true)) {
            throw new InvalidArgumentException(
                sprintf('module %s: %s needs itself through its constructors.', $wiringScope->module->name, $class),
            );
        }

        $arguments = [];

        foreach ($this->required($class) as $reflectionParameter) {
            $arguments[] = $this->argument($class, $reflectionParameter, $wiringScope, [...$path, $class]);
        }

        return new WiredObject($class, '', $arguments);
    }

    /**
     * @param class-string $class A class.
     *
     * @return list<ReflectionParameter> Its constructor's parameters up to the first with a default.
     */
    private function required(string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $required = [];

        foreach ($constructor->getParameters() as $reflectionParameter) {
            if ($reflectionParameter->isOptional()) {
                break;
            }

            $required[] = $reflectionParameter;
        }

        return $required;
    }

    /**
     * @param string              $class               The class whose constructor asks.
     * @param ReflectionParameter $reflectionParameter The parameter.
     * @param WiringScope         $wiringScope         What it may be wired from.
     * @param list<string>        $path                The classes being built, this one included.
     *
     * @throws InvalidArgumentException When the parameter cannot be wired.
     *
     * @return WiredObject The argument.
     */
    private function argument(
        string $class,
        ReflectionParameter $reflectionParameter,
        WiringScope $wiringScope,
        array $path,
    ): WiredObject {
        $reflectionNamedType = $this->namedType($class, $reflectionParameter, $wiringScope);
        $root = $this->tagged($reflectionParameter) ?? $this->root($reflectionNamedType, $reflectionParameter->getName(), $wiringScope);

        if ($root instanceof WiredObject) {
            return $root;
        }

        if ($reflectionNamedType->isBuiltin()) {
            $why = 'the ' . $reflectionNamedType->getName() . ' type, which is no root of this side and no #[Tagged] plugin list';

            throw $this->refusal($wiringScope, $class, $reflectionParameter, $why);
        }

        return $this->service($class, $reflectionParameter, $reflectionNamedType->getName(), $wiringScope, $path);
    }

    /**
     * @param string              $class               The class whose constructor asks.
     * @param ReflectionParameter $reflectionParameter The parameter.
     * @param WiringScope         $wiringScope         The module being wired.
     *
     * @throws InvalidArgumentException When the parameter is nullable, a union, variadic or untyped.
     *
     * @return ReflectionNamedType Its one, non-null type.
     */
    private function namedType(
        string $class,
        ReflectionParameter $reflectionParameter,
        WiringScope $wiringScope,
    ): ReflectionNamedType {
        $type = $reflectionParameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->allowsNull() || $reflectionParameter->isVariadic()) {
            throw $this->refusal($wiringScope, $class, $reflectionParameter, 'a nullable, union, variadic or untyped parameter');
        }

        return $type;
    }

    /**
     * @param ReflectionParameter $reflectionParameter A parameter.
     *
     * @return WiredObject|null The plugin list it takes, when it is marked `#[Tagged]`; else null.
     */
    private function tagged(ReflectionParameter $reflectionParameter): ?WiredObject
    {
        foreach ($reflectionParameter->getAttributes(Tagged::class) as $reflectionAttribute) {
            return new WiredObject($reflectionAttribute->newInstance()->type, WiredObject::TAGGED, []);
        }

        return null;
    }

    /**
     * @param ReflectionNamedType $reflectionNamedType A parameter's type.
     * @param string              $name                Its name.
     * @param WiringScope         $wiringScope         The side's roots.
     *
     * @return WiredObject|null The root it is, or null.
     */
    private function root(
        ReflectionNamedType $reflectionNamedType,
        string $name,
        WiringScope $wiringScope,
    ): ?WiredObject {
        $type = $reflectionNamedType->getName();
        $roots = $wiringScope->roots;

        if ($reflectionNamedType->isBuiltin() && array_key_exists($name, $roots->names)) {
            return new WiredObject($type, $roots->names[$name], []);
        }

        return array_key_exists($type, $roots->types)
            ? new WiredObject($type, $roots->types[$type], [])
            : null;
    }

    /**
     * @param string              $class               The class whose constructor asks.
     * @param ReflectionParameter $reflectionParameter The parameter.
     * @param string              $type                The class or interface it asks for.
     * @param WiringScope         $wiringScope         What it may be wired from.
     * @param list<string>        $path                The classes being built.
     *
     * @throws InvalidArgumentException When no factory, binding or reachable readonly class answers it.
     *
     * @return WiredObject The service.
     */
    private function service(
        string $class,
        ReflectionParameter $reflectionParameter,
        string $type,
        WiringScope $wiringScope,
        array $path,
    ): WiredObject {
        if (array_key_exists($type, $wiringScope->factories)) {
            [$factory, $method] = $wiringScope->factories[$type];

            return new WiredObject($type, '', [$this->wire($factory, $wiringScope, $path)], $method);
        }

        if (interface_exists($type) && array_key_exists($type, $wiringScope->bindings)) {
            return $this->wire($wiringScope->bindings[$type], $wiringScope, $path);
        }

        if (!class_exists($type) || !$this->buildable($type, $wiringScope)) {
            $why = $type . ', which no visible module makes or binds and which is not a readonly class'
                . ' of the app, the framework or a required module';

            throw $this->refusal($wiringScope, $class, $reflectionParameter, $why);
        }

        return $this->wire($type, $wiringScope, $path);
    }

    /**
     * @param class-string $type        A class a constructor asks for.
     * @param WiringScope  $wiringScope What it may be wired from.
     *
     * @return bool Whether the build may construct it: reachable, readonly and instantiable.
     */
    private function buildable(string $type, WiringScope $wiringScope): bool
    {
        $reflectionClass = new ReflectionClass($type);

        return $wiringScope->reaches($type) && $reflectionClass->isReadOnly() && $reflectionClass->isInstantiable();
    }

    /**
     * @param WiringScope         $wiringScope         The module being wired.
     * @param string              $class               The class whose constructor asks.
     * @param ReflectionParameter $reflectionParameter The parameter.
     * @param string              $why                 What it asks for.
     *
     * @return InvalidArgumentException The refusal.
     */
    private function refusal(
        WiringScope $wiringScope,
        string $class,
        ReflectionParameter $reflectionParameter,
        string $why,
    ): InvalidArgumentException {
        return new InvalidArgumentException(sprintf(
            'module %s: cannot wire $%s of %s: it asks for %s.',
            $wiringScope->module->name,
            $reflectionParameter->getName(),
            $class,
            $why,
        ));
    }
}
