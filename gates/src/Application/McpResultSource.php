<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\McpField;
use Gates\Domain\McpToolSpec;
use Gates\Domain\McpType;
use InvalidArgumentException;
use JsonSerializable;
use Override;

use function array_unique;
use function array_values;
use function implode;
use function in_array;
use function ksort;
use function strtr;
use function usort;

/**
 * Writes a tool's result (#316), `App\Generated\<Tool>Result`: the tool's
 * output as the JSON its output schema states, each field by name, an enum as
 * its value, each value class and list of them through its own method.
 */
final readonly class McpResultSource
{
    private const array SCALAR_ORDER = ['bool', 'float', 'int', 'string'];

    private const string TEMPLATE = <<<'PHP'
        final readonly class {result} implements JsonSerializable
        {
            /**
             * @param {output} ${outputVariable} The tool's output.
             */
            public function __construct(
                private {output} ${outputVariable},
            ) {
            }

            /**
             * @return {doc} The output, as the tool's output schema states it.
             */
            #[Override]
            public function jsonSerialize(): array
            {
                return $this->{outputVariable}($this->{outputVariable});
            }

        {methods}
        }
        PHP;

    private const string OBJECT = <<<'PHP'
            /**
             * @param {short} ${variable} A value.
             *
             * @return {doc} Its JSON.
             */
            private function {variable}({short} ${variable}): array
            {
                return [
        {fields}
                ];
            }
        PHP;

    private const string LIST = <<<'PHP'
            /**
             * @param list<{short}> ${variable}s The values.
             *
             * @return list<{doc}> Their JSON.
             */
            private function {variable}s(array ${variable}s): array
            {
                $encoded = [];

                foreach (${variable}s as ${variable}) {
                    $encoded[] = {each};
                }

                return $encoded;
            }
        PHP;

    /**
     * @param PhpClassFile    $phpClassFile    Emits checked generated source.
     * @param McpCode         $mcpCode         The code pieces.
     * @param McpSchemaSource $mcpSchemaSource Finds the value classes an output holds.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private McpCode $mcpCode = new McpCode(),
        private McpSchemaSource $mcpSchemaSource = new McpSchemaSource(),
    ) {
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     * @param string      $from        Where it is, for the generated note.
     *
     * @throws InvalidArgumentException When generated imports conflict.
     *
     * @return string The result's file.
     */
    public function file(McpToolSpec $mcpToolSpec, string $from): string
    {
        $output = $mcpToolSpec->output;
        $methods = [];
        $classes = [JsonSerializable::class, Override::class];

        foreach ($this->mcpSchemaSource->objects($output) as $mcpType) {
            $classes[] = $mcpType->php;
            $methods[] = $this->object($mcpType);
            $methods = [...$methods, ...$this->lists($mcpType)];
        }

        $source = strtr(self::TEMPLATE, [
            '{doc}' => $this->doc($output),
            '{methods}' => implode("\n\n", array_values(array_unique($methods))),
            '{outputVariable}' => $this->mcpCode->variable($output->php),
            '{output}' => $this->mcpCode->short($output->php),
            '{result}' => $this->mcpCode->short($mcpToolSpec->class) . 'Result',
        ]);

        $docblock = $this->phpClassFile->generated($from, 'the ' . $mcpToolSpec->toolInfo->name . " tool's output as JSON (#316).");

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param McpType $mcpType A value class.
     *
     * @return string Its method.
     */
    private function object(McpType $mcpType): string
    {
        $variable = '$' . $this->mcpCode->variable($mcpType->php);
        $fields = [];

        foreach ($mcpType->fields as $mcpField) {
            $encoded = $this->encoded($mcpField, $variable);
            $fields[$mcpField->name] = '            ' . $this->mcpCode->value($mcpField->name) . ' => ' . $encoded . ',';
        }

        ksort($fields);

        return strtr(self::OBJECT, [
            '{doc}' => $this->doc($mcpType),
            '{fields}' => implode("\n", array_values($fields)),
            '{short}' => $this->mcpCode->short($mcpType->php),
            '{variable}' => $this->mcpCode->variable($mcpType->php),
        ]);
    }

    /**
     * @param McpType $mcpType A value class.
     *
     * @return list<string> A list method for each list of value classes or enums among its fields.
     */
    private function lists(McpType $mcpType): array
    {
        $lists = [];

        foreach ($mcpType->fields as $mcpField) {
            $items = $mcpField->mcpType->items;

            if (!$items instanceof McpType || $items->kind === McpType::SCALAR) {
                continue;
            }

            $lists[] = $this->listMethod($items);
        }

        return $lists;
    }

    /**
     * @param McpType $mcpType A list's member type: a value class or an enum.
     *
     * @return string The method that writes such a list.
     */
    private function listMethod(McpType $mcpType): string
    {
        $variable = $this->mcpCode->variable($mcpType->php);
        $each = $mcpType->kind === McpType::ENUM
            ? '$' . $variable . '->value'
            : '$this->' . $variable . '($' . $variable . ')';

        return strtr(self::LIST, [
            '{doc}' => $this->doc($mcpType),
            '{each}' => $each,
            '{short}' => $this->mcpCode->short($mcpType->php),
            '{variable}' => $variable,
        ]);
    }

    /**
     * @param McpField $mcpField A field.
     * @param string   $variable The value's variable.
     *
     * @return string Its JSON value.
     */
    private function encoded(McpField $mcpField, string $variable): string
    {
        $mcpType = $mcpField->mcpType;
        $read = $variable . '->' . $mcpField->name;
        $items = $mcpType->items instanceof McpType
            ? $mcpType->items
            : $mcpType;
        $listed = '$this->' . $this->mcpCode->variable($items->php) . 's(' . $read . ')';

        return match (true) {
            $mcpType->kind === McpType::ENUM => $read . '->value',
            $mcpType->kind === McpType::OBJECT => '$this->' . $this->mcpCode->variable($mcpType->php) . '(' . $read . ')',
            $mcpType->kind === McpType::LIST && $items->kind !== McpType::SCALAR => $listed,
            default => $read,
        };
    }

    /**
     * @param McpType $mcpType A type.
     *
     * @return string Its JSON's PHPDoc type.
     */
    private function doc(McpType $mcpType): string
    {
        return match ($mcpType->kind) {
            McpType::OBJECT => 'array<string, ' . $this->union($mcpType) . '>',
            McpType::LIST => 'list<' . $this->doc($mcpType->items ?? $mcpType) . '>',
            McpType::ENUM => $mcpType->json === 'integer' ? 'int' : 'string',
            default => $mcpType->php,
        };
    }

    /**
     * @param McpType $mcpType A value class.
     *
     * @return string The union of its fields' JSON types: scalars first, then lists and objects, then null.
     */
    private function union(McpType $mcpType): string
    {
        $members = [];
        $nullable = false;

        foreach ($mcpType->fields as $mcpField) {
            $members[] = $this->doc($mcpField->mcpType);
            $nullable = $nullable || $mcpField->mcpType->nullable;
        }

        $members = array_values(array_unique($members));
        usort($members, $this->compare(...));

        return implode('|', [...$members, ...($nullable ? ['null'] : [])]);
    }

    /**
     * @param string $first  A PHPDoc type.
     * @param string $second Another.
     *
     * @return int The scalars first in their order, then the rest by name.
     */
    private function compare(string $first, string $second): int
    {
        $scalar = [in_array($first, self::SCALAR_ORDER, true), in_array($second, self::SCALAR_ORDER, true)];

        return match (true) {
            $scalar[0] && !$scalar[1] => -1,
            !$scalar[0] && $scalar[1] => 1,
            default => $first <=> $second,
        };
    }
}
