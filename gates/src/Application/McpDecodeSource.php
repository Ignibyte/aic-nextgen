<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Mcp\Contracts\McpNullableArguments;
use Gates\Domain\McpField;
use Gates\Domain\McpToolSpec;
use Gates\Domain\McpType;
use LogicException;

use function array_values;
use function implode;
use function strtr;

/**
 * Writes the body of a tool adapter's `call()` (#316): the fields it takes,
 * the defaults it fills, each field read as its type into the input, and the
 * tool called with it.
 */
final readonly class McpDecodeSource
{
    private const array READERS = ['int' => 'int', 'float' => 'float', 'string' => 'string', 'bool' => 'bool'];

    private const string ENUM = <<<'PHP'
            /**
             * @param {type} $value One of its values, checked against them.
             *
             * @throws LogicException When it is none of them.
             *
             * @return {short} Its case.
             */
            private function {variable}({type} $value): {short}
            {
                return match ($value) {
        {arms}
                    default => throw new LogicException('The checked value is no case of {short}.'),
                };
            }
        PHP;

    /**
     * @param McpCode $mcpCode The code pieces.
     */
    public function __construct(
        private McpCode $mcpCode = new McpCode(),
    ) {
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     *
     * @return string The body.
     */
    public function body(McpToolSpec $mcpToolSpec): string
    {
        $names = [];
        $reads = [];

        foreach ($mcpToolSpec->input->fields as $mcpField) {
            $names[] = $mcpField->name;
            $reads[] = $this->read($mcpField);
        }

        $input = $this->mcpCode->short($mcpToolSpec->input->php);
        $inputVariable = '$' . $this->mcpCode->variable($input);
        $tool = $this->mcpCode->short($mcpToolSpec->class);
        $actor = $mcpToolSpec->withActor
            ? ', $actor'
            : '';
        $call = '$this->' . $this->mcpCode->variable($tool) . '->call(' . $inputVariable . $actor . ')';

        return implode("\n", [
            '        $mcpArguments->only(' . $this->mcpCode->list($names) . ');',
            ...$this->defaults($mcpToolSpec),
            $this->mcpCode->call('new ' . $input, $reads, 8, $inputVariable . ' = ', ';'),
            '',
            '        return new ' . $tool . 'Result(' . $call . ');',
        ]);
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     *
     * @return list<string> The classes the body reads with: the nullable reader, and each enum with the exception its
     *                      method throws.
     */
    public function classes(McpToolSpec $mcpToolSpec): array
    {
        $classes = [];

        foreach ($mcpToolSpec->input->fields as $mcpField) {
            $classes = match (true) {
                $mcpField->mcpType->kind === McpType::ENUM => [...$classes, LogicException::class, $mcpField->mcpType->php],
                $mcpField->mcpType->nullable => [...$classes, McpNullableArguments::class],
                default => $classes,
            };
        }

        return $classes;
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     *
     * @return list<string> A method per enum the input reads, each turning a checked value into its case.
     */
    public function enums(McpToolSpec $mcpToolSpec): array
    {
        $methods = [];

        foreach ($mcpToolSpec->input->fields as $mcpField) {
            if ($mcpField->mcpType->kind !== McpType::ENUM) {
                continue;
            }

            $methods[$mcpField->mcpType->php] = $this->enumMethod($mcpField->mcpType);
        }

        return array_values($methods);
    }

    /**
     * @param McpToolSpec $mcpToolSpec The tool.
     *
     * @return list<string> The line that fills the input's defaults, when it has any.
     */
    private function defaults(McpToolSpec $mcpToolSpec): array
    {
        $defaults = [];

        foreach ($mcpToolSpec->input->fields as $mcpField) {
            if (!$mcpField->defaulted()) {
                continue;
            }

            $defaults[] = '"' . $mcpField->name . '":' . $mcpField->default;
        }

        if ($defaults === []) {
            return [];
        }

        $json = $this->mcpCode->value('{' . implode(',', $defaults) . '}');

        return ['        $filled = $mcpArguments->withDefaults(' . $json . ');'];
    }

    /**
     * @param McpField $mcpField An input field.
     *
     * @return string The read of its value from the arguments.
     */
    private function read(McpField $mcpField): string
    {
        $mcpType = $mcpField->mcpType;
        $reader = $mcpField->defaulted()
            ? '$filled'
            : '$mcpArguments';
        $name = $this->mcpCode->value($mcpField->name);

        $reader = $mcpType->nullable
            ? '(new McpNullableArguments(' . $reader . '))'
            : $reader;

        return match ($mcpType->kind) {
            McpType::ENUM => $this->enum($mcpType, $reader, $name),
            McpType::LIST => $reader . '->' . ($this->ofIntegers($mcpType) ? 'ints' : 'strings') . '(' . $name . ')',
            default => $reader . '->' . (self::READERS[$mcpType->php] ?? 'string') . '(' . $name . ')',
        };
    }

    /**
     * @param McpType $mcpType An enum type.
     * @param string  $reader  The arguments' variable.
     * @param string  $name    The field's name, as a literal.
     *
     * @return string The read of its value, checked against the enum's values, into its case.
     */
    private function enum(McpType $mcpType, string $reader, string $name): string
    {
        $choice = $mcpType->json === 'integer'
            ? 'intChoice'
            : 'choice';

        $read = $reader . '->' . $choice . '(' . $name . ', ' . $this->mcpCode->list($mcpType->choices) . ')';

        return '$this->' . $this->mcpCode->variable($mcpType->php) . '(' . $read . ')';
    }

    /**
     * @param McpType $mcpType An enum type.
     *
     * @return string Its method.
     */
    private function enumMethod(McpType $mcpType): string
    {
        $short = $this->mcpCode->short($mcpType->php);
        $arms = [];

        foreach ($mcpType->cases as $index => $case) {
            $arms[] = '            ' . $this->mcpCode->value($mcpType->choices[$index] ?? '') . ' => ' . $short . '::' . $case . ',';
        }

        return strtr(self::ENUM, [
            '{arms}' => implode("\n", $arms),
            '{short}' => $short,
            '{type}' => $mcpType->json === 'integer' ? 'int' : 'string',
            '{variable}' => $this->mcpCode->variable($mcpType->php),
        ]);
    }

    /**
     * @param McpType $mcpType A list type.
     *
     * @return bool Whether it holds integers; an input list holds strings otherwise.
     */
    private function ofIntegers(McpType $mcpType): bool
    {
        return $mcpType->items instanceof McpType && $mcpType->items->php === 'int';
    }
}
