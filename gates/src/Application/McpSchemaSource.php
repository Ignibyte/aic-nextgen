<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Mcp\Domain\JsonSchema;
use Gates\Domain\McpField;
use Gates\Domain\McpType;
use InvalidArgumentException;

use function array_values;
use function implode;
use function ksort;
use function strtr;

/**
 * Writes the JSON schema of one value class an MCP tool takes or returns
 * (#316), `App\Generated\<Value>Schema`: `schema()` gives the object with its
 * fields in key order and the required ones, and each field's schema is its
 * own method, from its type, description and default; a nested value class's
 * comes from that class's schema.
 */
final readonly class McpSchemaSource
{
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
            /**
             * @param string $description What the value is; empty for a tool's input or output.
             *
             * @return JsonSchema The schema of a {short}.
             */
            public function schema(string $description = ''): JsonSchema
            {
                return new JsonSchema(
                    'object',
                    $description,
                    properties: [
        {properties}
                    ],
                    required: {required},
                );
            }

        {fields}
        }
        PHP;

    private const string FIELD = <<<'PHP'
            /**
             * @return JsonSchema The schema of its {name}.
             */
            private function {method}(): JsonSchema
            {
        {body}
            }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Emits checked generated source.
     * @param McpCode      $mcpCode      The code pieces.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private McpCode $mcpCode = new McpCode(),
    ) {
    }

    /**
     * @param McpType $mcpType A value class's object type.
     * @param string  $from    Where it is, for the generated note.
     *
     * @throws InvalidArgumentException When generated imports conflict.
     *
     * @return string Its schema class's file.
     */
    public function file(McpType $mcpType, string $from): string
    {
        $properties = [];
        $fields = [];
        $classes = [JsonSchema::class];

        foreach ($mcpType->fields as $mcpField) {
            $method = $mcpField->name . 'Field';
            $properties[$mcpField->name] = '                ' . $this->mcpCode->value($mcpField->name) . ' => $this->' . $method . '(),';
            $fields[] = strtr(self::FIELD, ['{name}' => $mcpField->name, '{method}' => $method, '{body}' => $this->body($mcpField)]);
            $classes = [...$classes, ...$this->nested($mcpField->mcpType)];
        }

        ksort($properties);
        $source = strtr(self::TEMPLATE, [
            '{class}' => $this->name($mcpType),
            '{fields}' => implode("\n\n", $fields),
            '{properties}' => implode("\n", array_values($properties)),
            '{required}' => $this->mcpCode->list($this->required($mcpType)),
            '{short}' => $this->mcpCode->short($mcpType->php),
        ]);

        $docblock = $this->phpClassFile->generated($from, "a value class's JSON schema (#316).");

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param McpType $mcpType An object type.
     *
     * @return string Its schema class's name.
     */
    public function name(McpType $mcpType): string
    {
        return $this->mcpCode->short($mcpType->php) . 'Schema';
    }

    /**
     * @param McpType $mcpType A type.
     *
     * @return list<McpType> Every object type it holds, itself first, each once.
     */
    public function objects(McpType $mcpType): array
    {
        $objects = $mcpType->kind === McpType::OBJECT
            ? [$mcpType->php => $mcpType]
            : [];

        foreach ($this->held($mcpType) as $type) {
            $objects += $this->keyed($this->objects($type));
        }

        return array_values($objects);
    }

    /**
     * @param McpType $mcpType An object type.
     *
     * @return list<string> The fields with no default, which a value must have.
     */
    private function required(McpType $mcpType): array
    {
        $required = [];

        foreach ($mcpType->fields as $mcpField) {
            if ($mcpField->defaulted()) {
                continue;
            }

            $required[] = $mcpField->name;
        }

        return $required;
    }

    /**
     * @param McpField $mcpField A field.
     *
     * @return string Its method's body.
     */
    private function body(McpField $mcpField): string
    {
        $mcpType = $mcpField->mcpType;
        $description = $this->mcpCode->value($mcpField->description);

        if ($mcpType->kind === McpType::OBJECT) {
            return '        return (new ' . $this->name($mcpType) . '())->schema(' . $description . ');';
        }

        $json = $this->mcpCode->value($mcpType->kind === McpType::LIST ? 'array' : $mcpType->json);
        $args = [$json, $description, ...$this->named($mcpType)];

        if ($mcpField->defaulted() && $mcpType->kind !== McpType::LIST) {
            $args = [...$args, 'defaulted: true', 'default: ' . $this->mcpCode->literal($mcpField->default)];
        }

        return $this->mcpCode->call('new JsonSchema', $args, 8, 'return ', ';');
    }

    /**
     * @param McpType $mcpType A type that is not an object.
     *
     * @return list<string> The schema's named arguments for what the type holds: its choices, its items, null.
     */
    private function named(McpType $mcpType): array
    {
        return match (true) {
            $mcpType->kind === McpType::ENUM => ['choices: ' . $this->mcpCode->list($mcpType->choices)],
            $mcpType->kind === McpType::LIST => ['items: ' . $this->items($mcpType->items ?? $mcpType)],
            $mcpType->nullable => ['nullable: true'],
            default => [],
        };
    }

    /**
     * @param McpType $mcpType A list's member type.
     *
     * @return string Its schema.
     */
    private function items(McpType $mcpType): string
    {
        $json = $this->mcpCode->value($mcpType->json);

        return match ($mcpType->kind) {
            McpType::OBJECT => '(new ' . $this->name($mcpType) . '())->schema()',
            McpType::ENUM => 'new JsonSchema(' . $json . ', choices: ' . $this->mcpCode->list($mcpType->choices) . ')',
            default => 'new JsonSchema(' . $json . ')',
        };
    }

    /**
     * @param McpType $mcpType A field's type.
     *
     * @return list<string> The schema class it uses, when it is or holds a value class.
     */
    private function nested(McpType $mcpType): array
    {
        $object = $mcpType->items instanceof McpType
            ? $mcpType->items
            : $mcpType;

        return $object->kind === McpType::OBJECT
            ? ['App\\Generated\\' . $this->name($object)]
            : [];
    }

    /**
     * @param McpType $mcpType A type.
     *
     * @return list<McpType> The types directly in it: a list's members and an object's fields.
     */
    private function held(McpType $mcpType): array
    {
        $held = $mcpType->items instanceof McpType
            ? [$mcpType->items]
            : [];

        foreach ($mcpType->fields as $mcpField) {
            $held[] = $mcpField->mcpType;
        }

        return $held;
    }

    /**
     * @param list<McpType> $objects Object types.
     *
     * @return array<string, McpType> Them by class.
     */
    private function keyed(array $objects): array
    {
        $keyed = [];

        foreach ($objects as $object) {
            $keyed[$object->php] = $object;
        }

        return $keyed;
    }
}
