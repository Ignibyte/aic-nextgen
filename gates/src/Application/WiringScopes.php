<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\Binding;
use AIC\Module\Contracts\ModuleSection;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiringRoots;
use Gates\Domain\WiringScope;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function array_key_exists;
use function array_map;
use function class_exists;
use function in_array;
use function sprintf;

/**
 * Builds what one module may be wired from (#92, #93): itself and the modules
 * it requires, their bindings, their factories' methods by return type, and
 * the roots of the side being wired.
 */
final readonly class WiringScopes
{
    /**
     * @param ModuleDefinition       $moduleDefinition The module to wire.
     * @param list<ModuleDefinition> $modules          Every module.
     * @param WiringRoots            $wiringRoots      The side's roots.
     *
     * @throws InvalidArgumentException When two factory methods make one type.
     *
     * @return WiringScope What its constructors may be wired from.
     */
    public function scope(ModuleDefinition $moduleDefinition, array $modules, WiringRoots $wiringRoots): WiringScope
    {
        $required = $moduleDefinition->entries(ModuleSection::Requires);
        $visible = [];
        $bindings = [];
        $factories = [];

        foreach ($modules as $module) {
            if ($module !== $moduleDefinition && !in_array($module->name, $required, true)) {
                continue;
            }

            $visible[] = $module->namespace;
            $bindings = [...$bindings, ...$this->bindings($module)];
            $factories = $this->factories($module, $factories);
        }

        $namespaces = array_map(static fn (ModuleDefinition $moduleDefinition): string => $moduleDefinition->namespace, $modules);

        return new WiringScope($moduleDefinition, $visible, $namespaces, $bindings, $factories, $wiringRoots);
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return array<string, class-string> Its bound classes by interface.
     */
    private function bindings(ModuleDefinition $moduleDefinition): array
    {
        $bindings = [];

        foreach ($moduleDefinition->declared(Binding::class) as $binding) {
            $bindings[$binding->interface] = $binding->class;
        }

        return $bindings;
    }

    /**
     * @param ModuleDefinition                           $moduleDefinition A module.
     * @param array<string, array{class-string, string}> $factories        The factory methods found so far, by type made.
     *
     * @throws InvalidArgumentException When two factory methods make one type.
     *
     * @return array<string, array{class-string, string}> Them with the module's (`EntryChecks` refuses a factory
     *                                                    that is not a class).
     */
    private function factories(ModuleDefinition $moduleDefinition, array $factories): array
    {
        foreach ($moduleDefinition->entries(ModuleSection::Factories) as $factory) {
            if (!class_exists($factory)) {
                continue;
            }

            foreach ($this->methods($factory) as $type => $method) {
                $this->checkUnmade($moduleDefinition, $factories, $type, $factory . '::' . $method . '()');
                $factories[$type] = [$factory, $method];
            }
        }

        return $factories;
    }

    /**
     * @param ModuleDefinition                           $moduleDefinition The module declaring the factory.
     * @param array<string, array{class-string, string}> $factories        The factory methods found so far, by type.
     * @param string                                     $type             The type a factory method makes.
     * @param string                                     $method           That method, for the refusal.
     *
     * @throws InvalidArgumentException When another factory method already makes the type.
     */
    private function checkUnmade(
        ModuleDefinition $moduleDefinition,
        array $factories,
        string $type,
        string $method,
    ): void {
        if (!array_key_exists($type, $factories)) {
            return;
        }

        $other = $factories[$type][0] . '::' . $factories[$type][1] . '()';

        throw new InvalidArgumentException(
            sprintf('module %s: %s and %s both make %s; keep one.', $moduleDefinition->name, $other, $method, $type),
        );
    }

    /**
     * @param class-string $factory A factory class.
     *
     * @return array<string, string> Its public instance methods that take nothing, by the class each returns.
     */
    private function methods(string $factory): array
    {
        $methods = [];

        foreach ((new ReflectionClass($factory))->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $type = $reflectionMethod->getReturnType();

            if (
                $reflectionMethod->isStatic()
                || $reflectionMethod->isConstructor()
                || !$type instanceof ReflectionNamedType
                || !class_exists($type->getName())
            ) {
                continue;
            }

            $methods[$type->getName()] = $reflectionMethod->getName();
        }

        return $methods;
    }
}
