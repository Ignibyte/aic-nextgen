<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Config\Domain\AppConfig;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
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
 * Resolves a module class's constructor at build time (#92), by reflection, into
 * the object graph the generated wiring builds.
 *
 * A parameter is a root the handler context gives (the app's settings, the
 * database and the envelope codec by type; `string $bootedAt` and
 * `string $root` by name), an interface bound by the module or a module it
 * requires, or a readonly class the scope reaches, built the same way.
 * Trailing parameters with defaults keep them. Anything else is refused with
 * the module, the class and the parameter: a scalar, an array, a nullable,
 * union or variadic type, a class that is not readonly, a module's class the
 * module does not require, or a cycle. Only readonly classes are built, so
 * sharing one instance between controllers is never observable.
 */
final readonly class Autowirer
{
    /**
     * The roots by type, as the `HandlerContext` accessor that gives each.
     */
    private const array ROOT_TYPES = [
        AppConfig::class => 'appConfig',
        Database::class => 'database',
        EnvelopeCodec::class => 'envelopeCodec',
    ];

    /**
     * The string roots by parameter name.
     */
    private const array ROOT_STRINGS = ['bootedAt', 'root'];

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
        $root = $this->root($reflectionNamedType->getName(), $reflectionParameter->getName());

        if ($root instanceof WiredObject) {
            return $root;
        }

        if ($reflectionNamedType->isBuiltin()) {
            $why = 'the scalar type ' . $reflectionNamedType->getName() . ', which is no root';

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
     * @param string $type A parameter's type.
     * @param string $name Its name.
     *
     * @return WiredObject|null The root it is, or null.
     */
    private function root(string $type, string $name): ?WiredObject
    {
        if ($type === 'string' && in_array($name, self::ROOT_STRINGS, true)) {
            return new WiredObject('string', $name, []);
        }

        return array_key_exists($type, self::ROOT_TYPES)
            ? new WiredObject($type, self::ROOT_TYPES[$type], [])
            : null;
    }

    /**
     * @param string              $class               The class whose constructor asks.
     * @param ReflectionParameter $reflectionParameter The parameter.
     * @param string              $type                The class or interface it asks for.
     * @param WiringScope         $wiringScope         What it may be wired from.
     * @param list<string>        $path                The classes being built.
     *
     * @throws InvalidArgumentException When no binding or reachable readonly class answers it.
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
        if (interface_exists($type) && array_key_exists($type, $wiringScope->bindings)) {
            return $this->wire($wiringScope->bindings[$type], $wiringScope, $path);
        }

        if (!class_exists($type) || !$this->buildable($type, $wiringScope)) {
            $why = $type . ', which no visible module binds and which is not a readonly class'
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
