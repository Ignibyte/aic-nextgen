<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Mcp\Attribute\PromptInfo;
use AIC\Mcp\Attribute\ResourceInfo;
use AIC\Mcp\Attribute\ToolInfo;
use AIC\Mcp\Contracts\McpPrompt;
use AIC\Mcp\Contracts\McpResource;
use AIC\Mcp\Contracts\McpTool;
use AIC\Module\Contracts\ModulePermission;
use Gates\Domain\McpModuleSpec;
use Gates\Domain\McpToolSpec;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_map;
use function basename;
use function class_exists;
use function glob;
use function is_a;
use function sort;

/**
 * Reads each module's MCP entries (#316): every class in its `src/Mcp/` that is
 * an `McpTool`, an `McpResource` or an `McpPrompt`, with its attribute and, for
 * a tool, its `call()` signature and the types it takes and returns. The build
 * writes the catalogs from what it reads; `McpChecks` holds the names and the
 * permissions across modules.
 */
final readonly class McpBuild
{
    /**
     * @param string        $root          The project root.
     * @param McpTypes      $mcpTypes      Reads the input and output classes.
     * @param McpChecks     $mcpChecks     Holds names, URIs and permissions.
     * @param McpReflection $mcpReflection Reads an entry's attribute and a tool's signature.
     */
    public function __construct(
        private string $root,
        private McpTypes $mcpTypes = new McpTypes(),
        private McpChecks $mcpChecks = new McpChecks(),
        private McpReflection $mcpReflection = new McpReflection(),
    ) {
    }

    /**
     * @param list<ModuleDefinition> $modules The app's modules.
     *
     * @throws InvalidArgumentException When an entry breaks a rule, naming the class and the rule.
     *
     * @return list<McpModuleSpec> Each module's entries, in module order.
     */
    public function modules(array $modules): array
    {
        $permissions = [];

        foreach ($modules as $moduleDefinition) {
            foreach ($moduleDefinition->declared(ModulePermission::class) as $modulePermission) {
                $permissions[] = $modulePermission->name;
            }
        }

        $specs = [];

        foreach ($modules as $module) {
            $specs[] = $this->module($module);
        }

        $this->mcpChecks->check($specs, $permissions);

        return $specs;
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @throws InvalidArgumentException When an entry breaks a rule.
     *
     * @return McpModuleSpec Its entries.
     */
    private function module(ModuleDefinition $moduleDefinition): McpModuleSpec
    {
        $files = glob($this->root . '/modules/' . $moduleDefinition->name . '/src/Mcp/*.php');
        $files = $files === false
            ? []
            : $files;
        sort($files);
        $classes = [];

        foreach ($files as $file) {
            $classes[] = $moduleDefinition->namespace . 'Mcp\\' . basename($file, '.php');
        }

        return new McpModuleSpec(
            $moduleDefinition,
            array_map($this->tool(...), $this->ofType($classes, McpTool::class)),
            $this->attributes($this->ofType($classes, McpResource::class), ResourceInfo::class),
            $this->attributes($this->ofType($classes, McpPrompt::class), PromptInfo::class),
        );
    }

    /**
     * @param list<string> $classes The classes of a module's `src/Mcp/`.
     * @param class-string $type    An entry interface.
     *
     * @return list<class-string> The ones of that type.
     */
    private function ofType(array $classes, string $type): array
    {
        $typed = [];

        foreach ($classes as $class) {
            if (!class_exists($class) || !is_a($class, $type, true)) {
                continue;
            }

            $typed[] = $class;
        }

        return $typed;
    }

    /**
     * @template T of object
     *
     * @param list<class-string> $classes   Resources or prompts.
     * @param class-string<T>    $attribute The attribute each carries.
     *
     * @throws InvalidArgumentException When one does not carry it once.
     *
     * @return array<class-string, T> Each one's attribute, by class.
     */
    private function attributes(array $classes, string $attribute): array
    {
        $attributes = [];

        foreach ($classes as $class) {
            $attributes[$class] = $this->mcpReflection->attribute($class, $attribute);
        }

        return $attributes;
    }

    /**
     * @param class-string $class A tool.
     *
     * @throws InvalidArgumentException When it has no `#[ToolInfo]` or its `call()` cannot be stated.
     *
     * @return McpToolSpec What it takes and returns.
     */
    private function tool(string $class): McpToolSpec
    {
        $toolInfo = $this->mcpReflection->attribute($class, ToolInfo::class);
        [$input, $output, $withActor] = $this->mcpReflection->signature($class);

        return new McpToolSpec(
            $class,
            $toolInfo,
            $this->mcpTypes->object($input, false),
            $this->mcpTypes->object($output, true),
            $withActor,
            $this->mcpReflection->throws($class),
        );
    }
}
