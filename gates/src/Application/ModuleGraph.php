<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleSection;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_key_exists;
use function implode;
use function in_array;

/**
 * Orders the modules by what they require (#92): a module comes after every
 * module it requires, and modules that do not depend on each other keep their
 * name order. A required name that is no module, or a cycle, is refused.
 */
final readonly class ModuleGraph
{
    /**
     * @param list<ModuleDefinition> $modules The modules, by name.
     *
     * @throws InvalidArgumentException When a module requires an unknown module, or the requires form a cycle.
     *
     * @return list<ModuleDefinition> The modules in build order.
     */
    public function order(array $modules): array
    {
        $byName = [];

        foreach ($modules as $module) {
            $byName[$module->name] = $module;
        }

        $ordered = [];

        foreach ($modules as $module) {
            $ordered = $this->place($module, $byName, $ordered, []);
        }

        return $ordered;
    }

    /**
     * @param ModuleDefinition                $moduleDefinition The module to place.
     * @param array<string, ModuleDefinition> $byName           Every module by name.
     * @param list<ModuleDefinition>          $ordered          The modules placed so far.
     * @param list<string>                    $path             The modules being placed, to find a cycle.
     *
     * @throws InvalidArgumentException When a required module is unknown, or the requires form a cycle.
     *
     * @return list<ModuleDefinition> The modules placed, this one included.
     */
    private function place(ModuleDefinition $moduleDefinition, array $byName, array $ordered, array $path): array
    {
        if (in_array($moduleDefinition, $ordered, true)) {
            return $ordered;
        }

        if (in_array($moduleDefinition->name, $path, true)) {
            $cycle = implode(' -> ', [...$path, $moduleDefinition->name]);

            throw new InvalidArgumentException('modules require each other in a cycle: ' . $cycle . '.');
        }

        foreach ($moduleDefinition->entries(ModuleSection::Requires) as $required) {
            if (!array_key_exists($required, $byName)) {
                throw new InvalidArgumentException(
                    'module ' . $moduleDefinition->name . ' requires "' . $required . '", which is no module.',
                );
            }

            $ordered = $this->place($byName[$required], $byName, $ordered, [...$path, $moduleDefinition->name]);
        }

        return [...$ordered, $moduleDefinition];
    }
}
