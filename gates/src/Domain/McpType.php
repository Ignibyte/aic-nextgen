<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * The type of one value of an MCP tool's input or output (#316), as the build
 * reads it from a constructor parameter: a scalar, a backed enum, a list of
 * one of those or of a value class, or a value class with its fields. The
 * schema, the decoding and the encoding are all written from it.
 */
final readonly class McpType
{
    public const string SCALAR = 'scalar';

    public const string ENUM = 'enum';

    public const string LIST = 'list';

    public const string OBJECT = 'object';

    /**
     * @param string           $kind     One of the four kinds.
     * @param string           $php      A scalar's PHP type (`int`), or the enum's or value class's name; empty for a list.
     * @param string           $json     Its JSON type (`integer`, `array`, `object`).
     * @param bool             $nullable Whether it allows null.
     * @param list<int|string> $choices  An enum's values.
     * @param list<string>     $cases    An enum's case names, in the order of its values.
     * @param McpType|null     $items    A list's member type.
     * @param list<McpField>   $fields   A value class's fields, in constructor order.
     */
    public function __construct(
        public string $kind,
        public string $php,
        public string $json,
        public bool $nullable = false,
        public array $choices = [],
        public array $cases = [],
        public ?self $items = null,
        public array $fields = [],
    ) {
    }
}
