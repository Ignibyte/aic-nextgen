<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_any;
use function str_starts_with;

/**
 * What one module's constructors may be wired from (#92): its own classes and
 * bindings, those of the modules it requires, the hand-written app's classes
 * outside every module, and the framework's.
 */
final readonly class WiringScope
{
    /**
     * @param ModuleDefinition            $module   The module being wired.
     * @param list<string>                $visible  The namespaces of the module and the modules it requires.
     * @param list<string>                $modules  The namespaces of every module.
     * @param array<string, class-string> $bindings The bound class by interface, from the module and the modules it requires.
     */
    public function __construct(
        public ModuleDefinition $module,
        public array $visible,
        public array $modules,
        public array $bindings,
    ) {
    }

    /**
     * Whether a concrete class may be built: the framework's, the hand-written
     * app's outside every module, or a visible module's.
     *
     * @param string $class A class a constructor asks for.
     *
     * @return bool True when it may be built.
     */
    public function reaches(string $class): bool
    {
        if (str_starts_with($class, 'AIC\\')) {
            return true;
        }

        foreach ($this->visible as $namespace) {
            if (str_starts_with($class, $namespace)) {
                return true;
            }
        }

        return str_starts_with($class, 'App\\') && !$this->inAnyModule($class);
    }

    /**
     * @param string $class A class name.
     *
     * @return bool Whether some module's namespace holds it.
     */
    private function inAnyModule(string $class): bool
    {
        return array_any($this->modules, static fn (string $namespace): bool => str_starts_with($class, $namespace));
    }
}
