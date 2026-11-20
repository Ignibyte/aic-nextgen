<?php

declare(strict_types=1);

namespace Gates\Application;

use BackedEnum;
use Gates\Domain\McpField;
use Gates\Domain\McpType;
use Gates\Domain\RegexCaptures;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

use function array_key_exists;
use function class_exists;
use function enum_exists;
use function file_get_contents;
use function in_array;
use function is_string;
use function json_encode;
use function ltrim;
use function preg_quote;
use function sprintf;
use function strrpos;
use function substr;
use function trim;

/**
 * Reads the value classes an MCP tool takes and returns (#316) into the types
 * the build writes schemas and code from: each constructor parameter a field,
 * with its type, its `@param` description and its default. An input holds
 * scalars, nullable scalars, backed enums and lists of strings or integers; an
 * output also holds value classes and lists of them. Anything else is refused,
 * naming the parameter.
 */
final readonly class McpTypes
{
    use RegexCaptures;

    private const array SCALARS = ['int' => 'integer', 'float' => 'number', 'string' => 'string', 'bool' => 'boolean'];

    private const array INPUT_LISTS = ['string', 'int'];

    /**
     * @param string       $class  A value class.
     * @param bool         $output Whether it is a tool's output, which may nest value classes.
     * @param list<string> $path   The classes it sits in, to refuse a cycle.
     *
     * @throws InvalidArgumentException When it is not a readonly class, or a parameter's type cannot be stated.
     *
     * @return McpType Its object type, with its fields.
     */
    public function object(string $class, bool $output, array $path = []): McpType
    {
        if (!class_exists($class) || !(new ReflectionClass($class))->isReadOnly() || in_array($class, $path, true)) {
            throw new InvalidArgumentException(sprintf('%s must be a readonly value class that does not hold itself (#316).', $class));
        }

        $fields = [];

        foreach ($this->parameters($class) as $reflectionParameter) {
            $fields[] = $this->field($reflectionParameter, $class, $output, [...$path, $class]);
        }

        return new McpType(McpType::OBJECT, $class, 'object', fields: $fields);
    }

    /**
     * @param class-string $class A value class.
     *
     * @return list<ReflectionParameter> Its constructor's parameters; none when it has no constructor.
     */
    private function parameters(string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        return $constructor instanceof ReflectionMethod
            ? $constructor->getParameters()
            : [];
    }

    /**
     * @param ReflectionParameter $reflectionParameter A constructor parameter.
     * @param string              $class               Its class.
     * @param bool                $output              Whether the class is (in) an output.
     * @param list<string>        $path                The classes it sits in.
     *
     * @throws InvalidArgumentException When its type cannot be stated or it has no description.
     *
     * @return McpField The field.
     */
    private function field(ReflectionParameter $reflectionParameter, string $class, bool $output, array $path): McpField
    {
        $where = $class . '::$' . $reflectionParameter->getName();
        $reflectionType = $reflectionParameter->getType();
        $doc = (string) $reflectionParameter->getDeclaringFunction()->getDocComment();
        $param = self::regexMatch('/@param\\s+(\\S+(?:,\\s*\\S+)*)\\s+\\$' . $reflectionParameter->getName() . '\\b[ \\t]*(.*)$/m', $doc);
        $description = trim($param[2] ?? '');

        if (!$reflectionType instanceof ReflectionNamedType || $description === '') {
            throw new InvalidArgumentException(sprintf('%s needs one named type and a @param description (#316).', $where));
        }

        $mcpType = $this->type($reflectionType, $param[1] ?? '', $where, $output, $path);
        $default = $reflectionParameter->isDefaultValueAvailable()
            ? $this->json($reflectionParameter)
            : '';

        return new McpField($reflectionParameter->getName(), $mcpType, $description, $default);
    }

    /**
     * @param ReflectionNamedType $reflectionNamedType The parameter's type.
     * @param string              $docType             Its `@param` type, for a list's members.
     * @param string              $where               The parameter, for a refusal.
     * @param bool                $output              Whether it is (in) an output.
     * @param list<string>        $path                The classes it sits in.
     *
     * @throws InvalidArgumentException When it cannot be stated.
     *
     * @return McpType Its type.
     */
    private function type(
        ReflectionNamedType $reflectionNamedType,
        string $docType,
        string $where,
        bool $output,
        array $path,
    ): McpType {
        $name = $reflectionNamedType->getName();

        if (array_key_exists($name, self::SCALARS)) {
            return new McpType(McpType::SCALAR, $name, self::SCALARS[$name], $reflectionNamedType->allowsNull());
        }

        return match (true) {
            $reflectionNamedType->allowsNull() => throw $this->refusal($where, 'only a scalar may be nullable'),
            $name === 'array' => $this->listOf($docType, $where, $output, $path),
            enum_exists($name) => $this->enum($name, $where),
            $output && class_exists($name) => $this->object($name, true, $path),
            default => throw $this->refusal($where, 'its type is not one a schema states'),
        };
    }

    /**
     * @param string       $docType The list's `@param` type, `list<…>`.
     * @param string       $where   The parameter, for a refusal.
     * @param bool         $output  Whether it is (in) an output.
     * @param list<string> $path    The classes it sits in.
     *
     * @throws InvalidArgumentException When it is not a list of a type the side takes.
     *
     * @return McpType The list's type.
     */
    private function listOf(string $docType, string $where, bool $output, array $path): McpType
    {
        $member = self::regexMatch('/^list<([\\w\\\\]+)>$/', $docType)[1] ?? '';

        if (array_key_exists($member, self::SCALARS) && ($output || in_array($member, self::INPUT_LISTS, true))) {
            return new McpType(McpType::LIST, '', 'array', items: new McpType(McpType::SCALAR, $member, self::SCALARS[$member]));
        }

        $class = $this->resolved($member, $where);

        return match (true) {
            !$output || $member === '' => throw $this->refusal($where, 'an input list holds strings or integers, named by @param list<…>'),
            enum_exists($class) => new McpType(McpType::LIST, '', 'array', items: $this->enum($class, $where)),
            class_exists($class) => new McpType(McpType::LIST, '', 'array', items: $this->object($class, true, $path)),
            default => throw $this->refusal($where, 'its @param list<…> names no value class'),
        };
    }

    /**
     * @param string $enum  An enum.
     * @param string $where The parameter, for a refusal.
     *
     * @throws InvalidArgumentException When it is not backed.
     *
     * @return McpType Its type: its backing type, its values the choices.
     */
    private function enum(string $enum, string $where): McpType
    {
        if (!enum_exists($enum)) {
            throw $this->refusal($where, $enum . ' is not an enum');
        }

        $reflectionEnum = new ReflectionEnum($enum);
        $backing = $reflectionEnum->getBackingType();

        if (!$backing instanceof ReflectionNamedType) {
            throw $this->refusal($where, $enum . ' must be a backed enum');
        }

        $choices = [];
        $cases = [];

        foreach ($reflectionEnum->getCases() as $reflectionEnumUnitCase) {
            $case = $reflectionEnumUnitCase->getValue();
            $choices[] = $case instanceof BackedEnum
                ? $case->value
                : '';
            $cases[] = $reflectionEnumUnitCase->getName();
        }

        $json = self::SCALARS[$backing->getName()] ?? 'string';

        return new McpType(McpType::ENUM, $enum, $json, choices: $choices, cases: $cases);
    }

    /**
     * @param string $member A list's member name from a `@param`.
     * @param string $where  The parameter (`Class::$name`), whose file's `use` lines and namespace resolve it.
     *
     * @return string The class it names; the name as given when nothing resolves it.
     */
    private function resolved(string $member, string $where): string
    {
        $class = substr($where, 0, (int) strrpos($where, '::'));
        $namespace = substr($class, 0, (int) strrpos($class, '\\'));

        if (class_exists($namespace . '\\' . $member) || enum_exists($namespace . '\\' . $member)) {
            return $namespace . '\\' . $member;
        }

        $file = class_exists($class)
            ? (new ReflectionClass($class))->getFileName()
            : false;
        $text = is_string($file)
            ? (string) file_get_contents($file)
            : '';
        $used = self::regexMatch('/^use ([\\w\\\\]+\\\\' . preg_quote($member, '/') . ');$/m', $text)[1] ?? '';

        return $used === ''
            ? ltrim($member, '\\')
            : $used;
    }

    /**
     * @param ReflectionParameter $reflectionParameter A parameter with a default.
     *
     * @return string The default as JSON, an enum as its value.
     */
    private function json(ReflectionParameter $reflectionParameter): string
    {
        $default = [$reflectionParameter->getDefaultValue()];

        return (string) json_encode($default[0] instanceof BackedEnum ? $default[0]->value : $default[0]);
    }

    /**
     * @param string $where The parameter.
     * @param string $why   What is wrong.
     *
     * @return InvalidArgumentException The refusal.
     */
    private function refusal(string $where, string $why): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('%s: %s (#316).', $where, $why));
    }
}
