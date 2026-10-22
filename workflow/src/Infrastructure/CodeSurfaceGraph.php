<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeModule;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Domain\SurfaceEntry;
use AIC\Workflow\Exception\WorkflowRefusal;

use function hash_file;
use function is_file;
use function str_starts_with;
use function strtolower;

/**
 * The build's surface in the code index (#106, droost-engine's route
 * pseudo-symbols, read from `build/surface.json` instead of routing YAML):
 * `route:GET /notes`, `command:greet`, `job:demo` and `module:note`, each
 * filed under its module's manifest, with `routes_to`, `handles`, `requires`
 * and `exports` edges.
 */
final readonly class CodeSurfaceGraph
{
    /**
     * The kinds whose entries are commands or jobs.
     */
    private const array NAMED = ['Command' => 'command', 'Job' => 'job'];

    /**
     * @param string         $root           The project's root.
     * @param SurfaceEntries $surfaceEntries Reads the surface.
     */
    public function __construct(
        private string $root,
        private SurfaceEntries $surfaceEntries,
    ) {
    }

    /**
     * @return string The surface's xxh128; empty when there is no surface.
     */
    public function hash(): string
    {
        $file = $this->root . '/build/surface.json';

        return is_file($file)
            ? (string) hash_file('xxh128', $file)
            : '';
    }

    /**
     * @throws WorkflowRefusal When the surface is not a surface manifest.
     *
     * @return array{list<CodeSymbol>, list<CodeEdge>} Its pseudo-symbols and edges.
     */
    public function graph(): array
    {
        $modules = $this->surfaceEntries->modules();
        $symbols = [];
        $edges = [];

        foreach ($modules as $module) {
            [$symbols, $edges] = $this->module($module, $symbols, $edges);
        }

        foreach ($this->surfaceEntries->entries(null) as $surfaceEntry) {
            [$symbols, $edges] = $this->entry($surfaceEntry, $modules, $symbols, $edges);
        }

        return [$symbols, $edges];
    }

    /**
     * @param CodeModule       $codeModule A module.
     * @param list<CodeSymbol> $symbols    The symbols so far.
     * @param list<CodeEdge>   $edges      The edges so far.
     *
     * @return array{list<CodeSymbol>, list<CodeEdge>} With the module's.
     */
    private function module(CodeModule $codeModule, array $symbols, array $edges): array
    {
        $name = 'module:' . $codeModule->name;
        $manifest = $this->manifest($codeModule->name);
        $symbols[] = new CodeSymbol($name, 'module', $manifest, 0, $name, $codeModule->namespace);

        foreach ($codeModule->requires as $required) {
            $edges[] = new CodeEdge($name, 'module:' . $required, 'requires', $manifest);
        }

        foreach ($codeModule->exports as $export) {
            $edges[] = new CodeEdge($name, $export, 'exports', $manifest);
        }

        return [$symbols, $edges];
    }

    /**
     * @param SurfaceEntry     $surfaceEntry A route, command or job.
     * @param list<CodeModule> $modules      The modules, for a command's or a job's owner.
     * @param list<CodeSymbol> $symbols      The symbols so far.
     * @param list<CodeEdge>   $edges        The edges so far.
     *
     * @return array{list<CodeSymbol>, list<CodeEdge>} With the entry's.
     */
    private function entry(SurfaceEntry $surfaceEntry, array $modules, array $symbols, array $edges): array
    {
        $kind = self::NAMED[$surfaceEntry->kind] ?? 'route';
        $module = $kind === 'route'
            ? $surfaceEntry->module
            : $this->owner($surfaceEntry->class, $modules);
        $name = $kind . ':' . $surfaceEntry->key;
        $file = $this->manifest($module);
        $symbols[] = new CodeSymbol($name, $kind, $file, 0, $this->area($module), $surfaceEntry->kind);
        $edges[] = new CodeEdge($name, $surfaceEntry->class, $kind === 'route' ? 'routes_to' : 'handles', $file);

        return [$symbols, $edges];
    }

    /**
     * @param string $module A module's name; empty for none.
     *
     * @return string Its area; `project` for none.
     */
    private function area(string $module): string
    {
        return $module === ''
            ? 'project'
            : 'module:' . $module;
    }

    /**
     * @param string           $class   A class.
     * @param list<CodeModule> $modules The modules.
     *
     * @return string The module whose namespace holds it; empty when none does.
     */
    private function owner(string $class, array $modules): string
    {
        foreach ($modules as $module) {
            if (str_starts_with($class, $module->namespace)) {
                return $module->name;
            }
        }

        return '';
    }

    /**
     * @param string $module A module; empty for none.
     *
     * @return string Its manifest's path; empty for none.
     */
    private function manifest(string $module): string
    {
        return $module === ''
            ? ''
            : 'modules/' . strtolower($module) . '/' . strtolower($module) . '.module.php';
    }
}
