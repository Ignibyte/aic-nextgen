<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Access\Domain\Actor;
use Gates\Domain\RegexCaptures;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

use function class_exists;
use function count;
use function file_get_contents;
use function in_array;
use function is_string;
use function ltrim;
use function preg_quote;
use function sprintf;
use function str_starts_with;
use function strrchr;
use function substr;

/**
 * Reads what the build needs of an MCP entry's class (#316): its attribute,
 * once and on a readonly class, and a tool's `call()` signature.
 */
final readonly class McpReflection
{
    use RegexCaptures;

    /**
     * @param class-string $class A tool.
     *
     * @throws InvalidArgumentException When it has no public `call(<Input>[, Actor]): <Output>`.
     *
     * @return array{string, string, bool} The input class, the output class, and whether it takes the caller.
     */
    public function signature(string $class): array
    {
        $call = $this->call($class);
        $parameters = $call->getParameters();
        $input = $this->named($parameters[0] ?? null);
        $actor = $this->named($parameters[1] ?? null);
        $returned = $call->getReturnType();
        $output = $returned instanceof ReflectionNamedType
            ? $returned->getName()
            : '';

        if ($input === '' || $output === '' || !in_array($actor, ['', Actor::class], true)) {
            throw $this->shape($class);
        }

        return [$input, $output, $actor !== ''];
    }

    /**
     * @template T of object
     *
     * @param class-string    $class     An entry.
     * @param class-string<T> $attribute The attribute it must carry.
     *
     * @throws InvalidArgumentException When it is not readonly or does not carry it once.
     *
     * @return T The attribute.
     */
    public function attribute(string $class, string $attribute): object
    {
        $reflectionClass = new ReflectionClass($class);
        $attributes = $reflectionClass->getAttributes($attribute);

        if (count($attributes) !== 1 || !$reflectionClass->isReadOnly()) {
            throw new InvalidArgumentException(sprintf(
                '%s: an MCP entry is a readonly class marked #[%s] once (#316).',
                $class,
                substr((string) strrchr($attribute, '\\'), 1),
            ));
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param class-string $class A tool.
     *
     * @throws InvalidArgumentException When it has no public `call()` of one or two parameters.
     *
     * @return list<string> The classes its `call()` docblock names with `@throws`, resolved by its file's `use` lines and
     *                      namespace.
     */
    public function throws(string $class): array
    {
        $reflectionClass = new ReflectionClass($class);
        $doc = (string) $this->call($class)->getDocComment();
        $file = $reflectionClass->getFileName();
        $text = is_string($file)
            ? (string) file_get_contents($file)
            : '';
        $throws = [];

        foreach (self::regexColumn('/@throws\\s+(\\\\?[\\w\\\\]+)/', $doc, 1) as $name) {
            $throws[] = $this->resolved($name, $reflectionClass->getNamespaceName(), $text);
        }

        return $throws;
    }

    /**
     * @param class-string $class A tool.
     *
     * @throws InvalidArgumentException When it has no public `call()` of one or two parameters.
     *
     * @return ReflectionMethod Its `call()`.
     */
    private function call(string $class): ReflectionMethod
    {
        $reflectionClass = new ReflectionClass($class);
        $call = $reflectionClass->hasMethod('call')
            ? $reflectionClass->getMethod('call')
            : null;

        if (!$call instanceof ReflectionMethod || !$call->isPublic() || $call->getNumberOfParameters() > 2) {
            throw $this->shape($class);
        }

        return $call;
    }

    /**
     * @param ReflectionParameter|null $reflectionParameter A parameter, if there is one.
     *
     * @return string Its type's name; empty when there is none or it is not one named type.
     */
    private function named(?ReflectionParameter $reflectionParameter): string
    {
        $reflectionType = $reflectionParameter instanceof ReflectionParameter
            ? $reflectionParameter->getType()
            : null;

        return $reflectionType instanceof ReflectionNamedType
            ? $reflectionType->getName()
            : '';
    }

    /**
     * @param class-string $class A tool.
     *
     * @return InvalidArgumentException The refusal of its `call()`.
     */
    private function shape(string $class): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            '%s: a tool has one public call(<Input> $input[, Actor $actor]): <Output>, both readonly value classes (#316).',
            $class,
        ));
    }

    /**
     * @param string $name      A class as a docblock names it.
     * @param string $namespace The namespace of the file it is in.
     * @param string $text      That file.
     *
     * @return string The class it names.
     */
    private function resolved(string $name, string $namespace, string $text): string
    {
        $used = self::regexMatch('/^use ([\\w\\\\]+\\\\' . preg_quote($name, '/') . ');$/m', $text)[1] ?? '';

        return match (true) {
            str_starts_with($name, '\\') => ltrim($name, '\\'),
            $used !== '' => $used,
            class_exists($namespace . '\\' . $name) => $namespace . '\\' . $name,
            default => $name,
        };
    }
}
