<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Access\Domain\Actor;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Domain\McpToolCall;
use AIC\Mcp\Domain\McpToolInfo;
use Gates\Domain\McpToolSpec;
use InvalidArgumentException;
use JsonSerializable;
use Override;

use function array_keys;
use function array_map;
use function implode;
use function max;
use function str_pad;
use function strlen;
use function strtr;

/**
 * Writes a tool's adapter (#316), `App\Generated\<Tool>Call`: built with the
 * tool, it gives the tool's descriptor with both schemas, and turns a call's
 * arguments into the tool's input (unknown fields refused, defaults filled,
 * each field read as its type), calls the tool and wraps its output in the
 * tool's result class.
 */
final readonly class McpCallSource
{
    private const string TEMPLATE = <<<'PHP'
        final readonly class {call} implements McpToolCall
        {
            /**
             * @param {tool} ${toolVariable} The tool.
             */
            public function __construct(
                private {tool} ${toolVariable},
            ) {
            }

            /**
             * @return McpToolInfo The tool, with its input and output schemas.
             */
            #[Override]
            public function toolInfo(): McpToolInfo
            {
                return new McpToolInfo(
                    {name},
                    {description},
                    {needs},
                    (new {inputSchema}())->schema(),
                    (new {outputSchema}())->schema(),
                );
            }

            /**
             * @param McpArguments $mcpArguments The call's arguments.
             * @param Actor        $actor        Who calls.
             *
        {throws}
             *
             * @return JsonSerializable The tool's output.
             */
            #[Override]
            public function callTool(McpArguments $mcpArguments, Actor $actor): JsonSerializable
            {
        {body}
            }{enums}
        }
        PHP;

    /**
     * @param PhpClassFile    $phpClassFile    Emits checked generated source.
     * @param McpCode         $mcpCode         The code pieces.
     * @param McpSchemaSource $mcpSchemaSource Names the schema classes.
     * @param McpDecodeSource $mcpDecodeSource Reads each field.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private McpCode $mcpCode = new McpCode(),
        private McpSchemaSource $mcpSchemaSource = new McpSchemaSource(),
        private McpDecodeSource $mcpDecodeSource = new McpDecodeSource(),
    ) {
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     * @param string      $from        Where it is, for the generated note.
     *
     * @throws InvalidArgumentException When generated imports conflict.
     *
     * @return string The adapter's file.
     */
    public function file(McpToolSpec $mcpToolSpec, string $from): string
    {
        $tool = $this->mcpCode->short($mcpToolSpec->class);
        $source = strtr(self::TEMPLATE, [
            '{body}' => $this->mcpDecodeSource->body($mcpToolSpec),
            '{call}' => $tool . 'Call',
            '{description}' => $this->mcpCode->value($mcpToolSpec->toolInfo->description),
            '{enums}' => $this->enums($mcpToolSpec),
            '{inputSchema}' => $this->mcpSchemaSource->name($mcpToolSpec->input),
            '{name}' => $this->mcpCode->value($mcpToolSpec->toolInfo->name),
            '{needs}' => $this->mcpCode->value($mcpToolSpec->toolInfo->needs),
            '{outputSchema}' => $this->mcpSchemaSource->name($mcpToolSpec->output),
            '{throws}' => $this->throws($mcpToolSpec),
            '{toolVariable}' => $this->mcpCode->variable($tool),
            '{tool}' => $tool,
        ]);
        $docblock = $this->phpClassFile->generated($from, 'the ' . $mcpToolSpec->toolInfo->name . " tool's adapter (#316).");

        return $this->phpClassFile->file('App\\Generated', $this->classes($mcpToolSpec), [], $docblock, $source);
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     *
     * @return string The `@throws` lines of `callTool()`: the arguments', an unknown enum value's, and the tool's own.
     */
    private function throws(McpToolSpec $mcpToolSpec): string
    {
        $throws = ['McpArgumentException' => 'When the arguments do not fit the input.'];

        if ($this->mcpDecodeSource->enums($mcpToolSpec) !== []) {
            $throws['LogicException'] = 'When a checked value is no case of its enum.';
        }

        foreach ($mcpToolSpec->throws as $class) {
            $throws[$this->mcpCode->short($class)] ??= 'When the tool throws it.';
        }

        $width = max(array_map(strlen(...), array_keys($throws)));
        $lines = [];

        foreach ($throws as $short => $why) {
            $lines[] = '     * @throws ' . str_pad($short, $width) . ' ' . $why;
        }

        return implode("\n", $lines);
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     *
     * @return string Its enum methods, each after a blank line; empty for none.
     */
    private function enums(McpToolSpec $mcpToolSpec): string
    {
        $methods = '';

        foreach ($this->mcpDecodeSource->enums($mcpToolSpec) as $method) {
            $methods .= "\n\n" . $method;
        }

        return $methods;
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     *
     * @return list<string> The classes the adapter uses: the framework's, the tool, its input and the enums it reads.
     */
    private function classes(McpToolSpec $mcpToolSpec): array
    {
        $classes = [Actor::class, McpArgumentException::class, McpArguments::class, McpToolCall::class, McpToolInfo::class];
        $classes = [...$classes, JsonSerializable::class, Override::class, $mcpToolSpec->class, $mcpToolSpec->input->php];

        $generated = [$this->mcpSchemaSource->name($mcpToolSpec->input), $this->mcpSchemaSource->name($mcpToolSpec->output)];

        $classes = [...$classes, ...$mcpToolSpec->throws];

        foreach ([...$generated, $this->mcpCode->short($mcpToolSpec->class) . 'Result'] as $class) {
            $classes[] = 'App\\Generated\\' . $class;
        }

        return [...$classes, ...$this->mcpDecodeSource->classes($mcpToolSpec)];
    }
}
