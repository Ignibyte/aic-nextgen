<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Block\Contracts\Block;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\Stream;
use AIC\Module\Contracts\Binding;
use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\ModuleSection;
use AIC\Module\Contracts\PluginTag;
use AIC\Schema\Contracts\Migration;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

use function class_exists;
use function enum_exists;
use function interface_exists;
use function is_a;
use function sprintf;
use function str_starts_with;

/**
 * The checks on what a manifest declares (#92): every class sits in the
 * module's own namespace and is what it is declared as. A route is exactly one
 * of Page, Api or Stream; a binding's class is a readonly class implementing
 * its interface; a block, plugin or migration has no required constructor
 * parameter, so the generated wiring builds it with `new X()`; a migration
 * lives under `src/Migration/`.
 */
final readonly class DeclarationChecks
{
    /**
     * @param ModuleDefinition $moduleDefinition The module.
     *
     * @throws InvalidArgumentException When a declaration fails its check.
     */
    public function check(ModuleDefinition $moduleDefinition): void
    {
        foreach ($moduleDefinition->declared(ModuleRoute::class) as $moduleRoute) {
            $this->checkRoute($moduleDefinition, $moduleRoute->handler);
        }

        foreach ($moduleDefinition->declared(Binding::class) as $binding) {
            $this->checkBinding($moduleDefinition, $binding->interface, $binding->class);
        }

        $this->checkPlugins($moduleDefinition);
        $this->checkMigrations($moduleDefinition);

        foreach ($moduleDefinition->entries(ModuleSection::Exports) as $export) {
            $this->checkOwned($moduleDefinition, $export, 'export');
        }
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     *
     * @throws InvalidArgumentException When a block or plugin fails its check.
     */
    private function checkPlugins(ModuleDefinition $moduleDefinition): void
    {
        foreach ($moduleDefinition->entries(ModuleSection::Blocks) as $block) {
            $this->checkPlain($moduleDefinition, $block, Block::class, 'block');
        }

        foreach ($moduleDefinition->declared(PluginTag::class) as $pluginTag) {
            if (!interface_exists($pluginTag->type) || $pluginTag->type === Block::class) {
                $why = 'plugin type ' . $pluginTag->type . ' must be an interface other than Block; declare blocks in blocks()';

                throw $this->refusal($moduleDefinition, $why);
            }

            $this->checkPlain($moduleDefinition, $pluginTag->class, $pluginTag->type, 'plugin');
        }
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     *
     * @throws InvalidArgumentException When a migration fails its check or lives outside `src/Migration/`.
     */
    private function checkMigrations(ModuleDefinition $moduleDefinition): void
    {
        foreach ($moduleDefinition->entries(ModuleSection::Migrations) as $migration) {
            $this->checkPlain($moduleDefinition, $migration, Migration::class, 'migration');

            if (!str_starts_with($migration, $moduleDefinition->namespace . 'Migration\\')) {
                throw $this->refusal($moduleDefinition, 'migration ' . $migration . ' must live in src/Migration/');
            }
        }
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $class            A route's controller.
     *
     * @throws InvalidArgumentException When it is not the module's, or not exactly one HTTP kind.
     */
    private function checkRoute(ModuleDefinition $moduleDefinition, string $class): void
    {
        $this->checkOwned($moduleDefinition, $class, 'route');
        $kinds = 0;

        foreach ([Page::class, Api::class, Stream::class] as $kind) {
            if (!is_a($class, $kind, true)) {
                continue;
            }

            $kinds += 1;
        }

        if ($kinds !== 1) {
            throw $this->refusal($moduleDefinition, 'route ' . $class . ' must be exactly one of Page, Api or Stream');
        }
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $interface        The bound interface.
     * @param string           $class            The class bound to it.
     *
     * @throws InvalidArgumentException When the interface is none, or the class is no readonly implementation of it in the
     *                                  module or the framework.
     */
    private function checkBinding(ModuleDefinition $moduleDefinition, string $interface, string $class): void
    {
        $placed = $moduleDefinition->owns($class) || str_starts_with($class, 'AIC\\');
        $readonly = class_exists($class) && (new ReflectionClass($class))->isReadOnly();

        if (!$placed || !$readonly || !interface_exists($interface) || !is_a($class, $interface, true)) {
            $why = 'service ' . $class . ' must be a readonly class of the module or the framework'
                . ' implementing the interface ' . $interface;

            throw $this->refusal($moduleDefinition, $why);
        }
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $class            A block, plugin or migration.
     * @param class-string     $type             What it must implement.
     * @param string           $what             What it was declared as, for the message.
     *
     * @throws InvalidArgumentException When it is not the module's, does not implement the type, or has a required constructor parameter.
     */
    private function checkPlain(ModuleDefinition $moduleDefinition, string $class, string $type, string $what): void
    {
        $this->checkOwned($moduleDefinition, $class, $what);

        if ($this->requiredParameters($class) > 0 || !is_a($class, $type, true)) {
            $why = $what . ' ' . $class . ' must implement ' . $type . ' and take no required constructor parameter';

            throw $this->refusal($moduleDefinition, $why);
        }
    }

    /**
     * @param string $class A class.
     *
     * @return int How many parameters its constructor requires; 0 for none or no class.
     */
    private function requiredParameters(string $class): int
    {
        if (!class_exists($class)) {
            return 0;
        }

        $constructor = (new ReflectionClass($class))->getConstructor();

        if (!$constructor instanceof ReflectionMethod) {
            return 0;
        }

        return $constructor->getNumberOfRequiredParameters();
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $class            A declared class.
     * @param string           $what             What it was declared as, for the message.
     *
     * @throws InvalidArgumentException When it does not exist or is outside the module's namespace.
     */
    private function checkOwned(ModuleDefinition $moduleDefinition, string $class, string $what): void
    {
        $exists = class_exists($class) || interface_exists($class) || enum_exists($class);

        if (!$exists || !$moduleDefinition->owns($class)) {
            throw $this->refusal($moduleDefinition, $what . ' ' . $class . ' must be a class in ' . $moduleDefinition->namespace);
        }
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $why              What is wrong.
     *
     * @return InvalidArgumentException The refusal, naming the module.
     */
    private function refusal(ModuleDefinition $moduleDefinition, string $why): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('module %s: %s.', $moduleDefinition->name, $why));
    }
}
