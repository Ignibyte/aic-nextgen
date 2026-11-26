<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\McpModuleSpec;
use Gates\Domain\McpToolSpec;
use InvalidArgumentException;

use function explode;
use function sprintf;
use function strlen;

/**
 * Writes what the build makes of the modules' MCP entries (#316): for each
 * module with any, `App\Generated\<Module>Mcp`, built with an adapter per entry
 * and giving the `McpCatalog` an endpoint serves (in parts past seven entries,
 * #334); and the adapters, a tool's
 * `<Tool>Call` and `<Tool>Result`, a resource's `<Resource>Read`, a prompt's
 * `<Prompt>Get`, each built with its entry.
 */
final readonly class McpSource
{
    private const int LINE_LIMIT = 140;

    /**
     * @param McpCode          $mcpCode          The code pieces.
     * @param McpCallSource    $mcpCallSource    Writes a tool's adapter.
     * @param McpResultSource  $mcpResultSource  Writes a tool's result.
     * @param McpSchemaSource  $mcpSchemaSource  Writes a value class's schema.
     * @param McpAdapterSource $mcpAdapterSource Writes a resource's adapter.
     * @param McpPromptSource  $mcpPromptSource  Writes a prompt's adapter.
     * @param McpCatalogParts  $mcpCatalogParts  Writes the catalog, in parts past seven entries (#334).
     */
    public function __construct(
        private McpCode $mcpCode = new McpCode(),
        private McpCallSource $mcpCallSource = new McpCallSource(),
        private McpResultSource $mcpResultSource = new McpResultSource(),
        private McpSchemaSource $mcpSchemaSource = new McpSchemaSource(),
        private McpAdapterSource $mcpAdapterSource = new McpAdapterSource(),
        private McpPromptSource $mcpPromptSource = new McpPromptSource(),
        private McpCatalogParts $mcpCatalogParts = new McpCatalogParts(),
    ) {
    }

    /**
     * @param list<McpModuleSpec> $specs Every module's entries.
     *
     * @throws InvalidArgumentException When two entries would make one generated class, a line would pass 140
     *                                  characters, or imports conflict.
     *
     * @return array<string, string> Each generated file, by its name under `build/generated/`.
     */
    public function files(array $specs): array
    {
        $files = [];

        foreach ($specs as $spec) {
            if ($spec->empty()) {
                continue;
            }

            foreach ($this->entries($spec) as $name => $source) {
                $files = $this->added($files, $name, $source);
            }
        }

        return $files;
    }

    /**
     * @param McpModuleSpec $mcpModuleSpec A module's entries.
     *
     * @throws InvalidArgumentException When two of them would make one class, or imports conflict.
     *
     * @return array<string, string> The module's catalog and its entries' adapters and schemas, by file name.
     */
    private function entries(McpModuleSpec $mcpModuleSpec): array
    {
        $from = 'modules/' . $mcpModuleSpec->moduleDefinition->name . '/src/Mcp/';
        $files = $this->mcpCatalogParts->files($mcpModuleSpec, $from);

        foreach ($mcpModuleSpec->tools as $mcpToolSpec) {
            foreach ($this->tool($mcpToolSpec, $from) as $name => $source) {
                $files = $this->added($files, $name, $source);
            }
        }

        foreach ($mcpModuleSpec->resources as $class => $resourceInfo) {
            $source = $this->mcpAdapterSource->read($class, $resourceInfo, $from);
            $files = $this->added($files, $this->mcpCode->short($class) . 'Read.php', $source);
        }

        foreach ($mcpModuleSpec->prompts as $class => $promptInfo) {
            $source = $this->mcpPromptSource->get($class, $promptInfo, $from);
            $files = $this->added($files, $this->mcpCode->short($class) . 'Get.php', $source);
        }

        return $files;
    }

    /**
     * @param McpToolSpec $mcpToolSpec A tool.
     * @param string      $from        Its module's `src/Mcp/`.
     *
     * @throws InvalidArgumentException When imports conflict.
     *
     * @return array<string, string> Its adapter, its result and the schema of each value class it takes or returns.
     */
    private function tool(McpToolSpec $mcpToolSpec, string $from): array
    {
        $tool = $this->mcpCode->short($mcpToolSpec->class);
        $files = [
            $tool . 'Call.php' => $this->mcpCallSource->file($mcpToolSpec, $from),
            $tool . 'Result.php' => $this->mcpResultSource->file($mcpToolSpec, $from),
        ];
        $objects = [...$this->mcpSchemaSource->objects($mcpToolSpec->input), ...$this->mcpSchemaSource->objects($mcpToolSpec->output)];

        foreach ($objects as $object) {
            $files[$this->mcpSchemaSource->name($object) . '.php'] = $this->mcpSchemaSource->file($object, $from);
        }

        return $files;
    }

    /**
     * @param array<string, string> $files  The files so far.
     * @param string                $name   A file's name.
     * @param string                $source Its source.
     *
     * @throws InvalidArgumentException When another entry made a different file of that name, or a line passes 140
     *                                  characters.
     *
     * @return array<string, string> The files with it.
     */
    private function added(array $files, string $name, string $source): array
    {
        if (($files[$name] ?? $source) !== $source) {
            throw new InvalidArgumentException(sprintf(
                'two MCP entries or value classes would make build/generated/%s; give one another class name (#316).',
                $name,
            ));
        }

        foreach (explode("\n", $source) as $line) {
            if (strlen($line) > self::LINE_LIMIT) {
                throw new InvalidArgumentException(sprintf(
                    'build/generated/%s would hold a line over %d characters; shorten a description or a name (#316).',
                    $name,
                    self::LINE_LIMIT,
                ));
            }
        }

        $files[$name] = $source;

        return $files;
    }
}
