<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Mcp\Domain\McpCatalog;
use Gates\Domain\McpModuleSpec;
use Gates\Domain\McpToolSpec;
use InvalidArgumentException;

use function array_keys;
use function explode;
use function implode;
use function max;
use function sprintf;
use function str_repeat;
use function strlen;
use function strtr;

/**
 * Writes what the build makes of the modules' MCP entries (#316): for each
 * module with any, `App\Generated\<Module>Mcp`, built with an adapter per entry
 * and giving the `McpCatalog` an endpoint serves; and the adapters, a tool's
 * `<Tool>Call` and `<Tool>Result`, a resource's `<Resource>Read`, a prompt's
 * `<Prompt>Get`, each built with its entry.
 */
final readonly class McpSource
{
    private const int LINE_LIMIT = 140;

    private const string CATALOG = <<<'PHP'
        final readonly class {class}
        {
            /**
        {params}
             */
            public function __construct(
        {properties}
            ) {
            }

            /**
             * @return McpCatalog The module's tools, resources and prompts, each in its adapter.
             */
            public function catalog(): McpCatalog
            {
                return new McpCatalog(
                    [{tools}],
                    [{resources}],
                    [{prompts}],
                );
            }
        }
        PHP;

    /**
     * @param PhpClassFile     $phpClassFile     Emits checked generated source.
     * @param McpCode          $mcpCode          The code pieces.
     * @param McpCallSource    $mcpCallSource    Writes a tool's adapter.
     * @param McpResultSource  $mcpResultSource  Writes a tool's result.
     * @param McpSchemaSource  $mcpSchemaSource  Writes a value class's schema.
     * @param McpAdapterSource $mcpAdapterSource Writes a resource's adapter.
     * @param McpPromptSource  $mcpPromptSource  Writes a prompt's adapter.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private McpCode $mcpCode = new McpCode(),
        private McpCallSource $mcpCallSource = new McpCallSource(),
        private McpResultSource $mcpResultSource = new McpResultSource(),
        private McpSchemaSource $mcpSchemaSource = new McpSchemaSource(),
        private McpAdapterSource $mcpAdapterSource = new McpAdapterSource(),
        private McpPromptSource $mcpPromptSource = new McpPromptSource(),
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
        $files = [$mcpModuleSpec->moduleDefinition->studlyName() . 'Mcp.php' => $this->catalog($mcpModuleSpec, $from)];

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
     * @param McpModuleSpec $mcpModuleSpec A module's entries.
     * @param string        $from          Its `src/Mcp/`.
     *
     * @throws InvalidArgumentException When imports conflict.
     *
     * @return string The catalog's file.
     */
    private function catalog(McpModuleSpec $mcpModuleSpec, string $from): string
    {
        [$tools, $resources, $prompts] = $this->adapters($mcpModuleSpec);
        $entries = [...$tools, ...$resources, ...$prompts];
        $source = strtr(self::CATALOG, [
            '{class}' => $mcpModuleSpec->moduleDefinition->studlyName() . 'Mcp',
            '{params}' => implode("\n", $this->params(array_keys($entries))),
            '{prompts}' => $this->listed($prompts),
            '{properties}' => implode("\n", $this->properties(array_keys($entries))),
            '{resources}' => $this->listed($resources),
            '{tools}' => $this->listed($tools),
        ]);
        $docblock = $this->phpClassFile->generated($from, "the module's MCP catalog (#316).")
            . "\n * It is API for the module's MCP endpoint, which a module may add after its tools.\n *\n * @api";

        $classes = [McpCatalog::class, ...array_keys($entries)];

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param McpModuleSpec $mcpModuleSpec A module's entries.
     *
     * @return array{array<string, string>, array<string, string>, array<string, string>} Each tool's, resource's and
     *                                                                                    prompt's adapter class, by the entry's class.
     */
    private function adapters(McpModuleSpec $mcpModuleSpec): array
    {
        $tools = [];
        $resources = [];
        $prompts = [];

        foreach ($mcpModuleSpec->tools as $mcpToolSpec) {
            $tools[$mcpToolSpec->class] = $this->mcpCode->short($mcpToolSpec->class) . 'Call';
        }

        foreach (array_keys($mcpModuleSpec->resources) as $class) {
            $resources[$class] = $this->mcpCode->short($class) . 'Read';
        }

        foreach (array_keys($mcpModuleSpec->prompts) as $class) {
            $prompts[$class] = $this->mcpCode->short($class) . 'Get';
        }

        return [$tools, $resources, $prompts];
    }

    /**
     * @param list<string> $entries The entries' classes.
     *
     * @return list<string> The constructor's `@param` lines, aligned.
     */
    private function params(array $entries): array
    {
        $width = 0;

        foreach ($entries as $entry) {
            $width = max($width, strlen($this->mcpCode->short($entry)));
        }

        $params = [];

        foreach ($entries as $entry) {
            $short = $this->mcpCode->short($entry);
            $pad = str_repeat(' ', $width - strlen($short));
            $variable = $this->mcpCode->variable($short);
            $params[] = sprintf('     * @param %s%s $%s%s An entry, for its adapter.', $short, $pad, $variable, $pad);
        }

        return $params;
    }

    /**
     * @param list<string> $entries The entries' classes.
     *
     * @return list<string> The constructor's promoted properties.
     */
    private function properties(array $entries): array
    {
        $properties = [];

        foreach ($entries as $entry) {
            $properties[] = sprintf('        private %s $%s,', $this->mcpCode->short($entry), $this->mcpCode->variable($entry));
        }

        return $properties;
    }

    /**
     * @param array<string, string> $adapters Each entry's adapter class, by the entry's class.
     *
     * @return string The adapters, each built with its entry, for a list literal.
     */
    private function listed(array $adapters): string
    {
        $built = [];

        foreach ($adapters as $entry => $adapter) {
            $built[] = 'new ' . $adapter . '($this->' . $this->mcpCode->variable($entry) . ')';
        }

        return implode(', ', $built);
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
