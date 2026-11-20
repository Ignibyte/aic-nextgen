<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;
use stdClass;

/**
 * A JSON schema for one value of a tool's input or output (#316), as the build
 * writes it from the value's PHP type: `string`, `integer`, `number`,
 * `boolean`, `array` (with its `items`) or `object` (with its `properties`, all
 * but the defaulted ones `required`, nothing else allowed), with a
 * description, `null` allowed when the type is nullable, an `enum` for a backed
 * enum's values and a `default` when the parameter has one.
 */
final readonly class JsonSchema implements JsonSerializable
{
    /**
     * @param string                     $type        The JSON type.
     * @param string                     $description What the value is, from its `@param`; empty for none.
     * @param bool                       $nullable    Whether null is allowed.
     * @param array<string, JsonSchema>  $properties  An object's fields, by name, in order.
     * @param list<string>               $required    The object's fields a value must have.
     * @param JsonSchema|null            $items       A list's member schema.
     * @param list<int|string>           $choices     The values allowed, for a backed enum; empty for any.
     * @param bool                       $defaulted   Whether the value has a default.
     * @param bool|float|int|string|null $default     The default, when it has one.
     */
    public function __construct(
        public string $type,
        public string $description = '',
        public bool $nullable = false,
        public array $properties = [],
        public array $required = [],
        public ?self $items = null,
        public array $choices = [],
        public bool $defaulted = false,
        public bool|float|int|string|null $default = null,
    ) {
    }

    /**
     * @return array<string, bool|float|int|string|stdClass|JsonSchema|list<int|string|null>|array<string, JsonSchema>|null> The schema.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['type' => $this->nullable ? [$this->type, 'null'] : $this->type]
            + $this->described()
            + $this->object()
            + ($this->items instanceof self ? ['items' => $this->items] : [])
            + $this->choice()
            + ($this->defaulted ? ['default' => $this->default] : []);
    }

    /**
     * @return array<string, string> Its description, when it has one.
     */
    private function described(): array
    {
        return $this->description === ''
            ? []
            : ['description' => $this->description];
    }

    /**
     * @return array<string, bool|stdClass|list<string>|array<string, JsonSchema>> An object's fields, required ones and closure.
     */
    private function object(): array
    {
        if ($this->type !== 'object') {
            return [];
        }

        return [
            'additionalProperties' => false,
            'properties' => $this->properties === [] ? new stdClass() : $this->properties,
            'required' => $this->required,
        ];
    }

    /**
     * @return array<string, list<int|string|null>> The values allowed, null among them when it is nullable.
     */
    private function choice(): array
    {
        if ($this->choices === []) {
            return [];
        }

        return ['enum' => $this->nullable ? [...$this->choices, null] : $this->choices];
    }
}
